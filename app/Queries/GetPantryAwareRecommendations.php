<?php

namespace App\Queries;

use App\Data\Recommendations\RecommendationResult;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recommendations\RecommendationEngine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
        $results = $this->ranked($user, $servings);
        $pageSize = $perPage ?? (int) config('recommendations.per_page', 12);

        return new LengthAwarePaginator(
            $results->forPage($page, $pageSize)->values(),
            $results->count(),
            $pageSize,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  numeric-string|null  $servings
     * @return Collection<int, RecommendationResult>
     */
    public function ranked(User $user, ?string $servings = null): Collection
    {
        $pantry = $this->availablePantry->get($user);

        // Score and sort the full owned catalogue before slicing so every consumer receives one
        // globally ranked collection rather than independently loading or scoring database pages.
        return Recipe::query()->whereBelongsTo($user)->active()
            ->withExists(['favouritedByUsers as is_favourite' => fn ($query) => $query->whereKey($user->id)])
            ->with(['ingredients.ingredient', 'ingredients.ingredientPackage'])
            ->get()
            ->map(fn (Recipe $recipe): RecommendationResult => $this->engine->score($recipe, $pantry, $servings))
            ->sort($this->compare(...))
            ->values();
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

        $favouriteComparison = $right->isFavourite <=> $left->isFavourite;
        if ($favouriteComparison !== 0) {
            return $favouriteComparison;
        }

        $nameComparison = strcmp(Str::lower($left->recipe->name), Str::lower($right->recipe->name));

        return $nameComparison !== 0 ? $nameComparison : $left->recipe->id <=> $right->recipe->id;
    }
}
