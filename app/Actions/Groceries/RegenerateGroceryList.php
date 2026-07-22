<?php

namespace App\Actions\Groceries;

use App\Data\Groceries\GroceryRegenerationResult;
use App\Data\Groceries\GroceryRequirementData;
use App\Enums\GroceryItemSource;
use App\Models\DinnerPlan;
use App\Models\GroceryItem;
use App\Models\GroceryItemContribution;
use App\Models\GroceryList;
use App\Models\PlannedDinner;
use App\Models\PlannedDinnerRequirement;
use App\Services\Groceries\GroceryCalculator;
use Illuminate\Support\Facades\DB;

final readonly class RegenerateGroceryList
{
    public function __construct(
        private EnsureGroceryList $ensureGroceryList,
        private GroceryCalculator $calculator,
    ) {}

    public function handle(DinnerPlan $dinnerPlan): GroceryRegenerationResult
    {
        return DB::transaction(function () use ($dinnerPlan): GroceryRegenerationResult {
            $plan = DinnerPlan::query()->lockForUpdate()->findOrFail($dinnerPlan->id);
            $listId = $this->ensureGroceryList->handle($plan)->id;
            $list = GroceryList::query()->lockForUpdate()->findOrFail($listId);
            $existing = GroceryItem::query()->whereBelongsTo($list)
                ->where('source', GroceryItemSource::Generated)->orderBy('generation_key')->lockForUpdate()->get()->keyBy('generation_key');

            $dinnerIds = PlannedDinner::query()->whereBelongsTo($plan)->active()->pluck('id');
            $requirements = PlannedDinnerRequirement::query()->whereIn('planned_dinner_id', $dinnerIds)
                ->with('ingredient:id,category')->orderBy('id')->get()
                ->map(fn (PlannedDinnerRequirement $requirement): GroceryRequirementData => new GroceryRequirementData(
                    requirementId: $requirement->id,
                    ingredientId: (int) $requirement->ingredient_id,
                    ingredientName: $requirement->ingredient_name,
                    ingredientCategory: $requirement->ingredient?->category,
                    quantityType: $requirement->quantity_type,
                    nonExactStatus: $requirement->non_exact_status,
                    coverage: $requirement->coverage,
                    missingAmount: $requirement->missing_amount,
                    compatibilityKey: $requirement->compatibility_key,
                    quantityDescription: $requirement->quantity_description,
                    ingredientPackageId: $requirement->ingredient_package_id,
                    packageLabel: $requirement->package_label,
                    packageContentUnit: $requirement->package_content_unit,
                ));

            $calculated = $this->calculator->calculate($requirements);
            $activeKeys = [];
            $increased = [];
            $clearedOverrides = [];

            foreach ($calculated as $result) {
                $activeKeys[] = $result->generationKey;
                $item = $existing->get($result->generationKey);
                $oldAmount = $item?->calculated_amount;
                $didIncrease = $item !== null && $oldAmount !== null && $result->calculatedAmount !== null
                    && bccomp($result->calculatedAmount, $oldAmount, $this->scale()) > 0;
                $hadOverride = (bool) $item?->is_manually_adjusted;

                $values = [
                    'ingredient_id' => $result->ingredientId,
                    'ingredient_package_id' => $result->ingredientPackageId,
                    'name' => $result->name,
                    'calculated_amount' => $result->calculatedAmount,
                    'calculated_unit' => $result->calculatedUnit,
                    'quantity_description' => $result->quantityDescription,
                    'package_label' => $result->packageLabel,
                    'category' => $result->category,
                    'override_amount' => null,
                    'override_unit' => null,
                    'is_manually_adjusted' => false,
                ];

                if ($item === null) {
                    $item = GroceryItem::query()->create($values + [
                        'grocery_list_id' => $list->id,
                        'source' => GroceryItemSource::Generated,
                        'generation_key' => $result->generationKey,
                    ]);
                } else {
                    if ($didIncrease) {
                        $values['checked_at'] = null;
                        $values['previous_calculated_amount'] = $oldAmount;
                        $values['quantity_increased_at'] = now();
                    }
                    $item->update($values);
                }

                if ($didIncrease) {
                    $increased[] = $item->id;
                }
                if ($hadOverride) {
                    $clearedOverrides[] = $item->id;
                }

                GroceryItemContribution::query()->whereBelongsTo($item)->delete();
                foreach ($result->contributions as $contribution) {
                    GroceryItemContribution::query()->create([
                        'grocery_item_id' => $item->id,
                        'planned_dinner_requirement_id' => $contribution->requirementId,
                        'normalized_amount' => $contribution->normalizedAmount,
                    ]);
                }
            }

            GroceryItem::query()->whereBelongsTo($list)->where('source', GroceryItemSource::Generated)
                ->when($activeKeys !== [], fn ($query) => $query->whereNotIn('generation_key', $activeKeys))
                ->when($activeKeys === [], fn ($query) => $query)
                ->delete();
            $list->update(['regenerated_at' => now()]);

            return new GroceryRegenerationResult($increased, $clearedOverrides);
        }, attempts: 3);
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }
}
