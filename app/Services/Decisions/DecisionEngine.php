<?php

namespace App\Services\Decisions;

use App\Data\Decisions\DecisionCandidate;
use App\Data\Recommendations\RecommendationResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Applies deterministic variety only after pantry, favourite, and cooking-history factors.
 */
final readonly class DecisionEngine
{
    /**
     * @param  Collection<int, RecommendationResult>  $recommendations
     * @param  array<int, CarbonImmutable>  $lastCookedByRecipe
     * @param  list<int>  $excludedRecipeIds
     * @return Collection<int, DecisionCandidate>
     */
    public function decide(
        Collection $recommendations,
        array $lastCookedByRecipe,
        string $seed,
        int $round,
        array $excludedRecipeIds,
        int $limit,
    ): Collection {
        return $recommendations
            ->reject(fn (RecommendationResult $result): bool => in_array($result->recipe->id, $excludedRecipeIds, true))
            ->map(fn (RecommendationResult $result): DecisionCandidate => $this->candidate(
                $result,
                $lastCookedByRecipe[$result->recipe->id] ?? null,
                $seed,
                $round,
            ))
            ->sort($this->compare(...))
            ->take(max(0, $limit))
            ->values();
    }

    private function candidate(RecommendationResult $result, ?CarbonImmutable $lastCookedAt, string $seed, int $round): DecisionCandidate
    {
        $version = config('decisions.algorithm_version');
        if (! is_string($version) || $version === '') {
            throw new \LogicException('Decision algorithm version must be a non-empty string.');
        }

        $explanations = ["Pantry score {$result->score}; {$result->missingCount} missing, {$result->partialCount} partial, {$result->incompatibleCount} incompatible."];
        if ($result->isFavourite) {
            $explanations[] = 'Saved as a favourite.';
        }
        $explanations[] = $lastCookedAt === null
            ? 'Never cooked before.'
            : 'Last cooked '.$lastCookedAt->format('Y-m-d').'.';

        return new DecisionCandidate(
            $result,
            $lastCookedAt,
            hash('sha256', $version.'|'.$seed.'|'.$round.'|'.$result->recipe->id),
            $explanations,
        );
    }

    private function compare(DecisionCandidate $left, DecisionCandidate $right): int
    {
        $leftResult = $left->recommendation;
        $rightResult = $right->recommendation;
        $scoreComparison = bccomp($rightResult->score, $leftResult->score, (int) config('measurements.calculation_scale', 6));
        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        foreach (['incompatibleCount', 'missingCount', 'partialCount'] as $count) {
            $comparison = $leftResult->{$count} <=> $rightResult->{$count};
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        $favouriteComparison = $rightResult->isFavourite <=> $leftResult->isFavourite;
        if ($favouriteComparison !== 0) {
            return $favouriteComparison;
        }

        if ($left->lastCookedAt === null || $right->lastCookedAt === null) {
            $neverCookedComparison = ($right->lastCookedAt === null) <=> ($left->lastCookedAt === null);
            if ($neverCookedComparison !== 0) {
                return $neverCookedComparison;
            }
        } else {
            $lastCookedComparison = $left->lastCookedAt->getTimestamp() <=> $right->lastCookedAt->getTimestamp();
            if ($lastCookedComparison !== 0) {
                return $lastCookedComparison;
            }
        }

        $seededComparison = strcmp($left->seededOrderKey, $right->seededOrderKey);

        return $seededComparison !== 0
            ? $seededComparison
            : $leftResult->recipe->id <=> $rightResult->recipe->id;
    }
}
