<?php

namespace App\Livewire\Forms;

use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\PantryEntry;
use App\Models\User;
use App\Rules\PositiveDecimalQuantity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;
use Livewire\Form;

class PantryEntryForm extends Form
{
    public ?int $ingredient_id = null;

    public ?int $ingredient_package_id = null;

    public string $unit = '';

    public string $amount = '';

    public function setEntry(PantryEntry $entry): void
    {
        $this->ingredient_id = $entry->ingredient_id;
        $this->ingredient_package_id = $entry->ingredient_package_id;
        $this->unit = $entry->ingredient_package_id === null ? $entry->display_unit->value : '';
        $this->amount = $this->entryAmount($entry);
    }

    /** @return array{ingredient_id: int, ingredient_package_id: int|null, unit: string|null, amount: string} */
    public function validated(User $user): array
    {
        $validator = Validator::make($this->all(), [
            'ingredient_id' => ['required', 'integer', Rule::exists('ingredients', 'id')->where('user_id', $user->id)->whereNull('archived_at')],
            'ingredient_package_id' => ['nullable', 'integer'],
            'unit' => ['nullable', Rule::enum(UnitCode::class)],
            'amount' => ['required', new PositiveDecimalQuantity],
        ]);

        $validator->after(function (ValidationValidator $validator) use ($user): void {
            $ingredient = Ingredient::query()->whereBelongsTo($user)->active()->find($this->ingredient_id);
            if ($ingredient === null) {
                return;
            }

            $hasPackage = $this->ingredient_package_id !== null;
            $hasUnit = filled($this->unit);
            if ($hasPackage === $hasUnit) {
                $validator->errors()->add('unit', 'Choose either a direct unit or a package definition.');

                return;
            }

            if ($hasPackage && ! IngredientPackage::query()->whereBelongsTo($ingredient)->whereKey($this->ingredient_package_id)->exists()) {
                $validator->errors()->add('ingredient_package_id', 'The selected package does not belong to this ingredient.');
            }

            $unit = UnitCode::tryFrom($this->unit);
            if ($unit !== null && ($unit->measurementGroup() !== $ingredient->preferred_measurement_group
                || ($unit->measurementGroup() === MeasurementGroup::Count && $unit !== $ingredient->preferred_unit))) {
                $validator->errors()->add('unit', 'The selected unit is incompatible with this ingredient.');
            }
        });

        /** @var array{ingredient_id: int, ingredient_package_id: int|null, unit: string|null, amount: string} $validated */
        $validated = $validator->validate();

        return $validated;
    }

    private function entryAmount(PantryEntry $entry): string
    {
        $package = $entry->ingredientPackage;
        if ($package !== null && $package->hasKnownContents()) {
            return bcdiv($entry->total_normalized_amount, (string) $package->normalized_content_amount, 6);
        }

        if ($package !== null) {
            return $entry->total_normalized_amount;
        }

        return bcdiv($entry->total_normalized_amount, (string) $entry->display_unit?->factorToBase(), 6);
    }
}
