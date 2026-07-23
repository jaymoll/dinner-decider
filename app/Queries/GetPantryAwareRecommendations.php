<?php

namespace App\Queries;

use App\Data\Recommendations\RecommendationResult;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recommendations\RecommendationEngine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Produces a globally ranked, deterministic page of pantry-aware recipe results.
 */
final readonly class GetPantryAwareRecommendations
{
    public function __construct(
        private AvailablePantry $availablePantry,
        private RecommendationEngine $engine,
    ) {}

    /**
     * @param  numeric-string|null  $servings
     * @return LengthAwarePaginator<int, RecommendationResult>
     */
    public function get(User $user, ?string $servings = null, ?int $perPage = null, int $page = 1): LengthAwarePaginator
    {
        $pantry = $this->availablePantry->get($user);

        // Score and sort the full owned catalogue before slicing so every page reflects the same
        // global ranking rather than a database page ranked in isolation.
        $results = Recipe::query()->whereBelongsTo($user)->active()
            ->with(['ingredients.ingredient', 'ingredients.ingredientPackage'])
            ->get()
            ->map(fn (Recipe $recipe): RecommendationResult => $this->engine->score($recipe, $pantry, $servings))
            ->sort($this->compare(...))
            ->values();
        $pageSize = $perPage ?? (int) config('recommendations.per_page', 12);

        return new LengthAwarePaginator(
            $results->forPage($page, $pageSize)->values(),
            $results->count(),
            $pageSize,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function compare(RecommendationResult $left, RecommendationResult $right): int
    {
        $scoreComparison = bccomp($right->score, $left->score, (int) config('measurements.calculation_scale', 6));
        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        // Equal scores prefer fewer severe gaps, then stable human and database identifiers.
        foreach (['incompatibleCount', 'missingCount', 'partialCount'] as $count) {
            $comparison = $left->{$count} <=> $right->{$count};
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        $nameComparison = strcmp(Str::lower($left->recipe->name), Str::lower($right->recipe->name));

        return $nameComparison !== 0 ? $nameComparison : $left->recipe->id <=> $right->recipe->id;
    }
}
