<?php

namespace Tests\Feature\Ingredients;

use App\Actions\Ingredients\ArchiveIngredient;
use App\Actions\Ingredients\CreateIngredient;
use App\Actions\Ingredients\RestoreIngredient;
use App\Actions\Ingredients\UpdateIngredient;
use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class IngredientManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_user_can_create_an_ingredient_with_aliases_and_known_package_contents(): void
    {
        $user = User::factory()->create();

        $ingredient = app(CreateIngredient::class)->handle($user, [
            'name' => 'Chopped Tomatoes',
            'category' => 'Canned goods',
            'preferred_measurement_group' => MeasurementGroup::Mass->value,
            'preferred_unit' => UnitCode::Gram->value,
            'is_staple' => true,
            'aliases' => ['Tinned tomatoes'],
            'packages' => [[
                'package_type' => 'can',
                'label' => '400 g can',
                'content_amount' => '0.4',
                'content_unit' => UnitCode::Kilogram->value,
            ]],
        ]);

        $this->assertTrue($ingredient->is_staple);
        $this->assertSame('chopped tomatoes', $ingredient->normalized_name);
        $this->assertSame('tinned tomatoes', $ingredient->aliases->sole()->normalized_name);
        $this->assertSame('400.000000', $ingredient->packages->sole()->normalized_content_amount);
    }

    public function test_an_ingredient_can_be_updated_archived_and_restored(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->for($user)->create();

        app(UpdateIngredient::class)->handle($user, $ingredient, [
            'name' => 'Brown Rice',
            'category' => null,
            'preferred_measurement_group' => 'mass',
            'preferred_unit' => 'kg',
            'is_staple' => false,
            'aliases' => [],
            'packages' => [],
        ]);

        $this->assertSame('brown rice', $ingredient->refresh()->normalized_name);

        app(ArchiveIngredient::class)->handle($user, $ingredient);
        $this->assertNotNull($ingredient->refresh()->archived_at);
        $this->assertFalse(Ingredient::query()->active()->whereKey($ingredient)->exists());

        app(RestoreIngredient::class)->handle($user, $ingredient);
        $this->assertNull($ingredient->refresh()->archived_at);
    }

    public function test_another_user_cannot_change_an_ingredient(): void
    {
        $ingredient = Ingredient::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(ArchiveIngredient::class)->handle(User::factory()->create(), $ingredient);
    }
}
