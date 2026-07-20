<?php

namespace Database\Seeders;

use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StageOneCatalogueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->oldest('id')->first();

        if ($user === null) {
            return;
        }

        foreach ([
            ['Pasta', MeasurementGroup::Mass, UnitCode::Gram],
            ['Milk', MeasurementGroup::Volume, UnitCode::Millilitre],
            ['Onion', MeasurementGroup::Count, UnitCode::Piece],
            ['Garlic', MeasurementGroup::Count, UnitCode::Clove],
        ] as [$name, $group, $unit]) {
            Ingredient::query()->firstOrCreate(
                ['user_id' => $user->id, 'normalized_name' => Str::lower($name)],
                ['name' => $name, 'preferred_measurement_group' => $group, 'preferred_unit' => $unit],
            );
        }
    }
}
