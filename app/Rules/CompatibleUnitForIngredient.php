<?php

namespace App\Rules;

use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final readonly class CompatibleUnitForIngredient implements ValidationRule
{
    public function __construct(private Ingredient $ingredient) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $unit = is_string($value) ? UnitCode::tryFrom($value) : null;

        if ($unit === null || $unit->measurementGroup() !== $this->ingredient->preferred_measurement_group) {
            $fail('The selected unit is not compatible with this ingredient.');

            return;
        }

        if ($unit->measurementGroup() === MeasurementGroup::Count && $unit !== $this->ingredient->preferred_unit) {
            $fail('Semantic count units cannot be converted automatically.');
        }
    }
}
