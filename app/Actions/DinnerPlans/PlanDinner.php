<?php

namespace App\Actions\DinnerPlans;

use App\Enums\PlannedDinnerStatus;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use App\Models\Recipe;
use App\Models\User;
use App\Services\DinnerPlans\RequirementSnapshotter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class PlanDinner
{
    public function __construct(
        private EnsureDinnerPlan $ensureDinnerPlan,
        private RequirementSnapshotter $snapshotter,
        private ReconcilePlanReservations $reconcile,
    ) {}

    /** @param numeric-string $servings */
    public function handle(User $user, Recipe $recipe, string $servings, ?string $plannedDate = null): PlannedDinner
    {
        return $this->plan($user, $recipe, $servings, $plannedDate, false);
    }

    /** @param numeric-string $servings */
    public function handleArchived(User $user, Recipe $recipe, string $servings, ?string $plannedDate = null): PlannedDinner
    {
        return $this->plan($user, $recipe, $servings, $plannedDate, true);
    }

    /** @param numeric-string $servings */
    private function plan(User $user, Recipe $recipe, string $servings, ?string $plannedDate, bool $archived): PlannedDinner
    {
        Gate::forUser($user)->authorize('view', $recipe);
        $this->assertPositive($servings);
        $date = $this->date($plannedDate);

        if (($recipe->archived_at !== null) !== $archived) {
            throw new InvalidArgumentException($archived ? 'Only archived recipes can be planned from the archive.' : 'Archived recipes must be planned from the archive.');
        }

        $plan = $this->ensureDinnerPlan->handle($user);

        return DB::transaction(function () use ($plan, $recipe, $servings, $date): PlannedDinner {
            $lockedPlan = DinnerPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $lockedRecipe = Recipe::query()->where('user_id', $lockedPlan->user_id)->lockForUpdate()->findOrFail($recipe->id);
            $lockedRecipe->load(['ingredients.ingredient', 'ingredients.ingredientPackage', 'steps', 'categories', 'tags']);
            $position = ((int) PlannedDinner::query()->whereBelongsTo($lockedPlan)->active()->max('position')) + 1;

            $dinner = PlannedDinner::query()->create([
                'dinner_plan_id' => $lockedPlan->id,
                'recipe_id' => $lockedRecipe->id,
                'recipe_name' => $lockedRecipe->name,
                'recipe_description' => $lockedRecipe->description,
                'source_servings' => (string) $lockedRecipe->default_servings,
                'servings' => $servings,
                'preparation_minutes' => $lockedRecipe->preparation_minutes,
                'cooking_minutes' => $lockedRecipe->cooking_minutes,
                'difficulty' => $lockedRecipe->difficulty,
                'cuisine' => $lockedRecipe->cuisine,
                'meal_type' => $lockedRecipe->meal_type,
                'notes' => $lockedRecipe->notes,
                'image_path' => $lockedRecipe->image_path,
                'source_url' => $lockedRecipe->source_url,
                'recipe_steps' => $lockedRecipe->steps->map(fn ($step): array => ['position' => $step->position, 'instruction' => $step->instruction])->values()->all(),
                'recipe_categories' => $lockedRecipe->categories->pluck('name')->values()->all(),
                'recipe_tags' => $lockedRecipe->tags->pluck('name')->values()->all(),
                'planned_date' => $date,
                'status' => PlannedDinnerStatus::Planned,
                'position' => $position,
            ]);

            foreach ($lockedRecipe->ingredients as $line) {
                $dinner->requirements()->create($this->snapshotter->fromRecipeIngredient($line, $servings, (string) $lockedRecipe->default_servings));
            }

            $this->reconcile->handle($lockedPlan);

            return $dinner->refresh()->load(['requirements.reservations.pantryEntry']);
        }, attempts: 3);
    }

    /** @param numeric-string $value */
    private function assertPositive(string $value): void
    {
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value) || bccomp($value, '0', $this->scale()) <= 0) {
            throw new InvalidArgumentException('Servings must be a positive decimal value.');
        }
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! CarbonImmutable::hasFormat($value, 'Y-m-d')) {
            throw new InvalidArgumentException('The planned date must use ISO YYYY-MM-DD format.');
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value);
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }
}
