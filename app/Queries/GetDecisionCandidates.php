<?php

namespace App\Queries;

use App\Data\Decisions\DecisionCandidate;
use App\Enums\PlannedDinnerStatus;
use App\Models\PlannedDinner;
use App\Models\User;
use App\Services\Decisions\DecisionEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class GetDecisionCandidates
{
    public function __construct(
        private GetPantryAwareRecommendations $recommendations,
        private DecisionEngine $engine,
    ) {}

    /**
     * @param  numeric-string|null  $servings
     * @param  list<int>  $excludedRecipeIds
     * @return Collection<int, DecisionCandidate>
     */
    public function get(User $user, string $seed, int $round = 0, ?string $servings = null, array $excludedRecipeIds = [], ?int $limit = null): Collection
    {
        $ranked = $this->recommendations->ranked($user, $servings);
        $recipeIds = $ranked->pluck('recipe.id');

        /** @var array<int, CarbonImmutable> $lastCookedByRecipe */
        $lastCookedByRecipe = PlannedDinner::query()
            ->whereHas('dinnerPlan', fn ($query) => $query->whereBelongsTo($user))
            ->where('status', PlannedDinnerStatus::Cooked)
            ->whereIn('recipe_id', $recipeIds)
            ->whereNotNull('recipe_id')
            ->selectRaw('recipe_id, MAX(cooked_at) as last_cooked_at')
            ->groupBy('recipe_id')
            ->get()
            ->mapWithKeys(fn (PlannedDinner $dinner): array => [
                (int) $dinner->recipe_id => CarbonImmutable::parse((string) $dinner->getAttribute('last_cooked_at')),
            ])->all();

        return $this->engine->decide(
            $ranked,
            $lastCookedByRecipe,
            $seed,
            max(0, $round),
            $excludedRecipeIds,
            $limit ?? (int) config('decisions.result_limit', 3),
        );
    }
}
