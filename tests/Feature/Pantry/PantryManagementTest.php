<?php

namespace Tests\Feature\Pantry;

use App\Actions\Ingredients\UpdateIngredient;
use App\Actions\Pantry\AddPantryStock;
use App\Actions\Pantry\RemovePantryEntry;
use App\Actions\Pantry\UpdatePantryEntry;
use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\PantryEntry;
use App\Models\User;
use App\Queries\AvailablePantry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PantryManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_compatible_direct_stock_merges_exactly(): void
    {
        $user = User::factory()->create();
        $mass = Ingredient::factory()->for($user)->create();
        $volume = Ingredient::factory()->for($user)->create(['preferred_measurement_group' => MeasurementGroup::Volume, 'preferred_unit' => UnitCode::Millilitre]);
        $onion = Ingredient::factory()->for($user)->create(['preferred_measurement_group' => MeasurementGroup::Count, 'preferred_unit' => UnitCode::Piece]);

        $this->add($user, $mass, '1', 'kg');
        $this->add($user, $mass, '500', 'g');
        $this->add($user, $volume, '1', 'l');
        $this->add($user, $volume, '250', 'ml');
        $this->add($user, $onion, '2', 'piece');
        $this->add($user, $onion, '0.5', 'piece');

        $this->assertSame('1500.000000', $mass->pantryEntries()->value('total_normalized_amount'));
        $this->assertSame('1250.000000', $volume->pantryEntries()->value('total_normalized_amount'));
        $this->assertSame('2.500000', $onion->pantryEntries()->value('total_normalized_amount'));
    }

    public function test_known_package_sizes_combine_for_calculation_but_keep_display_rows(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $small = IngredientPackage::factory()->for($ingredient)->create(['label' => '400 g can', 'content_amount' => '400', 'normalized_content_amount' => '400']);
        $large = IngredientPackage::factory()->for($ingredient)->create(['label' => '800 g can', 'content_amount' => '800', 'normalized_content_amount' => '800']);

        $this->add($user, $ingredient, '1', packageId: $small->id);
        $this->add($user, $ingredient, '1', packageId: $large->id);
        $availability = app(AvailablePantry::class)->get($user);

        $this->assertCount(2, $availability->balances);
        $this->assertSame('1200.000000', $availability->buckets->get($ingredient->id.'|mass')->availableAmount);
    }

    public function test_unknown_packages_remain_definition_specific(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $first = IngredientPackage::factory()->unknownContents()->for($ingredient)->create();
        $second = IngredientPackage::factory()->unknownContents()->for($ingredient)->create(['label' => 'Large pack']);

        $this->add($user, $ingredient, '2', packageId: $first->id);
        $this->add($user, $ingredient, '1', packageId: $second->id);

        $this->assertCount(2, $ingredient->pantryEntries);
        $this->assertNotSame($ingredient->pantryEntries[0]->compatibility_key, $ingredient->pantryEntries[1]->compatibility_key);
    }

    public function test_foreign_archived_incompatible_and_non_positive_inputs_fail(): void
    {
        $user = User::factory()->create();
        $foreignIngredient = Ingredient::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        $this->add($user, $foreignIngredient, '1', 'g');
    }

    public function test_archived_and_incompatible_new_stock_is_rejected(): void
    {
        $user = User::factory()->create();
        $archived = Ingredient::factory()->archived()->for($user)->create();

        try {
            $this->add($user, $archived, '1', 'g');
            $this->fail('Archived ingredients must not accept new pantry rows.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $ingredient = Ingredient::factory()->for($user)->create();
        $this->expectException(InvalidArgumentException::class);
        $this->add($user, $ingredient, '1', 'ml');
    }

    public function test_zero_and_negative_stock_is_rejected(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();

        foreach (['0', '-1'] as $amount) {
            try {
                $this->add($user, $ingredient, $amount, 'g');
                $this->fail("Amount {$amount} should fail.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_referenced_package_content_cannot_change_or_be_deleted(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $package = IngredientPackage::factory()->for($ingredient)->create();
        $this->add($user, $ingredient, '1', packageId: $package->id);

        $this->expectException(InvalidArgumentException::class);
        app(UpdateIngredient::class)->handle($user, $ingredient, [
            'name' => $ingredient->name,
            'category' => $ingredient->category,
            'preferred_measurement_group' => $ingredient->preferred_measurement_group->value,
            'preferred_unit' => $ingredient->preferred_unit->value,
            'is_staple' => false,
            'aliases' => [],
            'packages' => [[
                'id' => $package->id,
                'package_type' => $package->package_type->value,
                'label' => $package->label,
                'content_amount' => '500',
                'content_unit' => 'g',
            ]],
        ]);
    }

    public function test_only_the_owner_can_update_or_remove_an_entry(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ingredient = Ingredient::factory()->for($owner)->create();
        $entry = $this->add($owner, $ingredient, '1', 'kg');

        $this->expectException(AuthorizationException::class);
        app(UpdatePantryEntry::class)->handle($other, $entry, '2');
    }

    public function test_updates_set_an_exact_positive_total_and_removal_is_separate(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();
        $entry = $this->add($user, $ingredient, '1', 'kg');

        app(UpdatePantryEntry::class)->handle($user, $entry, '0.75');
        $this->assertSame('750.000000', $entry->refresh()->total_normalized_amount);

        app(RemovePantryEntry::class)->handle($user, $entry);
        $this->assertModelMissing($entry);
    }

    private function add(User $user, Ingredient $ingredient, string $amount, ?string $unit = null, ?int $packageId = null): PantryEntry
    {
        return app(AddPantryStock::class)->handle($user, [
            'ingredient_id' => $ingredient->id,
            'amount' => $amount,
            'unit' => $unit,
            'ingredient_package_id' => $packageId,
        ]);
    }
}
