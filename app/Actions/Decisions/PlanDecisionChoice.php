<?php

namespace App\Actions\Decisions;

use App\Actions\DinnerPlans\PlanDinner;
use App\Models\PlannedDinner;
use App\Models\Recipe;
use App\Models\User;
use App\Queries\GetDecisionCandidates;
use InvalidArgumentException;

final readonly class PlanDecisionChoice
{
    public function __construct(
        private GetDecisionCandidates $candidates,
        private PlanDinner $planDinner,
    ) {}

    /**
     * @param  numeric-string|null  $servings
     * @param  list<mixed>  $excludedRecipeIds
     */
    public function handle(User $user, int $recipeId, string $seed, int $round, ?string $servings, array $excludedRecipeIds): PlannedDinner
    {
        $servings = filled($servings) ? $servings : null;
        if ($servings !== null && (! preg_match('/^\d+(?:\.\d+)?$/', $servings) || bccomp($servings, '0', (int) config('measurements.calculation_scale', 6)) <= 0)) {
            throw new InvalidArgumentException('Servings must be a positive decimal value.');
        }

        $recipe = Recipe::query()->whereBelongsTo($user)->active()->findOrFail($recipeId);
        /** @var list<int> $exclusions */
        $exclusions = $this->normalizeExclusions($excludedRecipeIds);
        if (in_array($recipe->id, $exclusions, true)) {
            throw new InvalidArgumentException('An excluded recipe cannot be planned from Decision Mode.');
        }

        $eligible = $this->candidates->get($user, $seed, max(0, $round), $servings, $exclusions)
            ->contains(fn ($candidate): bool => $candidate->recommendation->recipe->is($recipe));
        if (! $eligible) {
            throw new InvalidArgumentException('The selected recipe is no longer an eligible Decision Mode candidate.');
        }

        return $this->planDinner->handle(
            $user,
            $recipe,
            $servings ?? (string) $recipe->default_servings,
        );
    }

    /**
     * @param  list<mixed>  $excludedRecipeIds
     * @return list<int>
     */
    private function normalizeExclusions(array $excludedRecipeIds): array
    {
        $normalized = collect($excludedRecipeIds)
            ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->take((int) config('decisions.exclusion_limit', 24))
            ->all();

        return array_values($normalized);
    }
}
