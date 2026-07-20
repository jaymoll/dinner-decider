<?php

namespace Database\Factories;

use App\Enums\PackageType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientPackage>
 */
class IngredientPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ingredient_id' => Ingredient::factory(),
            'package_type' => PackageType::Can,
            'label' => '400 g can',
            'content_amount' => '400',
            'content_unit' => UnitCode::Gram,
            'normalized_content_amount' => '400',
        ];
    }

    public function unknownContents(): static
    {
        return $this->state(fn (): array => [
            'label' => 'Standard pack',
            'content_amount' => null,
            'content_unit' => null,
            'normalized_content_amount' => null,
        ]);
    }
}
