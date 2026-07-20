<?php

namespace App\Actions\DinnerPlans;

use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\RequirementCoverage;
use App\Models\DinnerPlan;
use App\Models\Ingredient;
use App\Models\IngredientReservation;
use App\Models\PantryEntry;
use App\Models\PlannedDinner;
use App\Models\PlannedDinnerRequirement;
use App\Services\DinnerPlans\PantryAllocator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ReconcilePlanReservations
{
    public function __construct(private PantryAllocator $allocator) {}

    /** @param list<int>|null $ingredientIds */
    public function handle(DinnerPlan $dinnerPlan, ?array $ingredientIds = null): void
    {
        DB::transaction(function () use ($dinnerPlan, $ingredientIds): void {
            $lockedPlan = DinnerPlan::query()->lockForUpdate()->findOrFail($dinnerPlan->id);
            $dinners = PlannedDinner::query()->whereBelongsTo($lockedPlan)->active()->priorityOrder()->lockForUpdate()->get();
            $requirements = PlannedDinnerRequirement::query()
                ->whereIn('planned_dinner_id', $dinners->modelKeys())
                ->when($ingredientIds !== null, fn ($query) => $query->whereIn('ingredient_id', $ingredientIds))
                ->orderBy('planned_dinner_id')->orderBy('position')->lockForUpdate()->get();

            $affectedIngredientIds = $requirements->pluck('ingredient_id')->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values();
            if ($ingredientIds !== null) {
                $affectedIngredientIds = $affectedIngredientIds->merge($ingredientIds)->map(fn ($id): int => (int) $id)->unique()->sort()->values();
            }

            $entries = PantryEntry::query()->where('user_id', $lockedPlan->user_id)
                ->whereIn('ingredient_id', $affectedIngredientIds)->oldest('id')->lockForUpdate()->get();

            IngredientReservation::query()
                ->whereHas('requirement', fn ($query) => $query
                    ->whereHas('plannedDinner', fn ($query) => $query->where('dinner_plan_id', $lockedPlan->id))
                    ->when($ingredientIds !== null, fn ($query) => $query->whereIn('ingredient_id', $ingredientIds)))
                ->oldest('id')->lockForUpdate()->get()->each->delete();

            $ingredients = Ingredient::query()->whereIn('id', $affectedIngredientIds)->get()->keyBy('id');
            $available = $entries->mapWithKeys(fn (PantryEntry $entry): array => [$entry->id => $entry->total_normalized_amount])->all();
            $requirementsByDinner = $requirements->groupBy('planned_dinner_id');

            foreach ($dinners as $dinner) {
                foreach ($requirementsByDinner->get($dinner->id, collect()) as $requirement) {
                    $this->reconcileRequirement($requirement, $ingredients, $entries, $available);
                }
            }
        }, attempts: 3);
    }

    /**
     * @param  Collection<int, Ingredient>  $ingredients
     * @param  Collection<int, PantryEntry>  $entries
     * @param  array<int, numeric-string>  $available
     */
    private function reconcileRequirement(PlannedDinnerRequirement $requirement, Collection $ingredients, Collection $entries, array &$available): void
    {
        $ingredient = $ingredients->get($requirement->ingredient_id);

        if ($requirement->quantity_type === QuantityType::NonExact) {
            $unavailable = $requirement->non_exact_status === NonExactStatus::Required
                && ($ingredient === null || ! $ingredient->is_currently_available);
            $requirement->update([
                'coverage' => $unavailable ? RequirementCoverage::Unavailable : RequirementCoverage::NonExact,
                'missing_amount' => null,
            ]);

            return;
        }

        $required = $requirement->scaled_amount ?? '0';
        if ($ingredient === null || ! $ingredient->is_currently_available) {
            $requirement->update(['coverage' => RequirementCoverage::Unavailable, 'missing_amount' => $required]);

            return;
        }

        if ($ingredient->is_staple) {
            $requirement->update(['coverage' => RequirementCoverage::Staple, 'missing_amount' => '0']);

            return;
        }

        $ingredientEntries = $entries->where('ingredient_id', $requirement->ingredient_id);
        $compatibleEntries = $ingredientEntries->where('compatibility_key', $requirement->compatibility_key);
        $candidates = array_values($compatibleEntries->map(fn (PantryEntry $entry): array => [
            'id' => $entry->id,
            'available_amount' => $available[$entry->id],
            'native' => $requirement->ingredient_package_id === $entry->ingredient_package_id,
        ])->all());
        $allocations = $this->allocator->allocate($required, $candidates);
        $reserved = '0';

        foreach ($allocations as $allocation) {
            IngredientReservation::query()->create([
                'planned_dinner_requirement_id' => $requirement->id,
                'pantry_entry_id' => $allocation->pantryEntryId,
                'normalized_amount' => $allocation->normalizedAmount,
            ]);
            $available[$allocation->pantryEntryId] = bcsub($available[$allocation->pantryEntryId], $allocation->normalizedAmount, $this->scale());
            $reserved = bcadd($reserved, $allocation->normalizedAmount, $this->scale());
        }

        $missing = bcsub($required, $reserved, $this->scale());
        $coverage = match (true) {
            bccomp($missing, '0', $this->scale()) === 0 => RequirementCoverage::Full,
            bccomp($reserved, '0', $this->scale()) > 0 => RequirementCoverage::Partial,
            $ingredientEntries->isNotEmpty() && $compatibleEntries->isEmpty() => RequirementCoverage::Incompatible,
            default => RequirementCoverage::Missing,
        };

        $requirement->update(['coverage' => $coverage, 'missing_amount' => $missing]);
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }
}
