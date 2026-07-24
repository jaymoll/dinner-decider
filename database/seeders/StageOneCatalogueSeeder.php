<?php

namespace Database\Seeders;

use App\Enums\MeasurementGroup;
use App\Enums\NonExactStatus;
use App\Enums\PackageType;
use App\Enums\QuantityType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\IngredientPackage;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\Tag;
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
        $user = User::query()->where('email', 'test@example.com')->first();

        if ($user === null) {
            return;
        }

        $ingredients = $this->seedIngredients($user);
        $packages = $this->seedPackages($ingredients);

        $this->seedPantry($user, $ingredients, $packages);
        $this->seedRecipes($user, $ingredients, $packages);
    }

    /** @return array<string, Ingredient> */
    private function seedIngredients(User $user): array
    {
        $ingredients = [];

        foreach ($this->ingredientDefinitions() as $key => $definition) {
            $ingredient = Ingredient::query()->updateOrCreate(
                ['user_id' => $user->id, 'normalized_name' => Str::lower($definition['name'])],
                [
                    'name' => $definition['name'],
                    'category' => $definition['category'],
                    'preferred_measurement_group' => $definition['group'],
                    'preferred_unit' => $definition['unit'],
                    'is_staple' => $definition['is_staple'],
                    'is_currently_available' => $definition['is_available'],
                    'archived_at' => $definition['is_archived'] ? now() : null,
                ],
            );

            foreach ($definition['aliases'] as $alias) {
                $ingredient->aliases()->updateOrCreate(
                    ['normalized_name' => Str::lower($alias)],
                    ['name' => $alias],
                );
            }

            $ingredients[$key] = $ingredient;
        }

        return $ingredients;
    }

    /**
     * @return array<string, array{name: string, category: string, group: MeasurementGroup, unit: UnitCode, is_staple: bool, is_available: bool, is_archived: bool, aliases: list<string>}>
     */
    private function ingredientDefinitions(): array
    {
        return [
            'spaghetti' => ['name' => 'Spaghetti', 'category' => 'Dry goods', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Pasta']],
            'rice' => ['name' => 'Rice', 'category' => 'Dry goods', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['White rice']],
            'chickpeas' => ['name' => 'Chickpeas', 'category' => 'Canned goods', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Garbanzo beans']],
            'tomatoes' => ['name' => 'Chopped Tomatoes', 'category' => 'Canned goods', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Canned tomatoes']],
            'coconut_milk' => ['name' => 'Coconut Milk', 'category' => 'Canned goods', 'group' => MeasurementGroup::Volume, 'unit' => UnitCode::Millilitre, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'onion' => ['name' => 'Onion', 'category' => 'Vegetables', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Piece, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Yellow onion']],
            'garlic' => ['name' => 'Garlic', 'category' => 'Vegetables', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Clove, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Garlic clove']],
            'olive_oil' => ['name' => 'Olive Oil', 'category' => 'Oils and condiments', 'group' => MeasurementGroup::Volume, 'unit' => UnitCode::Millilitre, 'is_staple' => true, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Extra virgin olive oil']],
            'salt' => ['name' => 'Salt', 'category' => 'Seasonings', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => true, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'black_pepper' => ['name' => 'Black Pepper', 'category' => 'Seasonings', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => true, 'is_available' => false, 'is_archived' => false, 'aliases' => ['Pepper']],
            'eggs' => ['name' => 'Eggs', 'category' => 'Dairy and eggs', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Piece, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Egg']],
            'milk' => ['name' => 'Milk', 'category' => 'Dairy and eggs', 'group' => MeasurementGroup::Volume, 'unit' => UnitCode::Millilitre, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'spinach' => ['name' => 'Baby Spinach', 'category' => 'Vegetables', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Spinach']],
            'parmesan' => ['name' => 'Parmesan', 'category' => 'Dairy and eggs', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Parmigiano Reggiano']],
            'chicken' => ['name' => 'Chicken Breast', 'category' => 'Meat', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'basil' => ['name' => 'Fresh Basil', 'category' => 'Herbs', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Leaf, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Basil']],
            'yoghurt' => ['name' => 'Plain Yoghurt', 'category' => 'Dairy and eggs', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Yogurt']],
            'flour' => ['name' => 'Plain Flour', 'category' => 'Baking', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => true, 'is_available' => true, 'is_archived' => false, 'aliases' => ['All-purpose flour']],
            'potatoes' => ['name' => 'Potatoes', 'category' => 'Vegetables', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Potato']],
            'carrot' => ['name' => 'Carrot', 'category' => 'Vegetables', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Piece, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Carrots']],
            'bell_pepper' => ['name' => 'Bell Pepper', 'category' => 'Vegetables', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Piece, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Sweet pepper']],
            'lemon' => ['name' => 'Lemon', 'category' => 'Fruit', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Piece, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'ginger' => ['name' => 'Fresh Ginger', 'category' => 'Vegetables', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Ginger root']],
            'bread' => ['name' => 'Wholegrain Bread', 'category' => 'Bakery', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Slice, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Bread']],
            'celery' => ['name' => 'Celery', 'category' => 'Vegetables', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Stalk, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'rosemary' => ['name' => 'Rosemary', 'category' => 'Herbs', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Sprig, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'lettuce' => ['name' => 'Lettuce', 'category' => 'Vegetables', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Leaf, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'garlic_bulb' => ['name' => 'Garlic Bulb', 'category' => 'Vegetables', 'group' => MeasurementGroup::Count, 'unit' => UnitCode::Bulb, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'kidney_beans' => ['name' => 'Kidney Beans', 'category' => 'Canned goods', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Red beans']],
            'tuna' => ['name' => 'Tuna', 'category' => 'Canned goods', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Canned tuna']],
            'cheddar' => ['name' => 'Cheddar', 'category' => 'Dairy and eggs', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'butter' => ['name' => 'Butter', 'category' => 'Dairy and eggs', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => true, 'is_available' => true, 'is_archived' => false, 'aliases' => []],
            'oats' => ['name' => 'Rolled Oats', 'category' => 'Breakfast', 'group' => MeasurementGroup::Mass, 'unit' => UnitCode::Gram, 'is_staple' => false, 'is_available' => true, 'is_archived' => false, 'aliases' => ['Oats']],
            'truffle_oil' => ['name' => 'Truffle Oil', 'category' => 'Oils and condiments', 'group' => MeasurementGroup::Volume, 'unit' => UnitCode::Millilitre, 'is_staple' => false, 'is_available' => true, 'is_archived' => true, 'aliases' => []],
        ];
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @return array<string, IngredientPackage>
     */
    private function seedPackages(array $ingredients): array
    {
        $definitions = [
            'chickpea_can' => [$ingredients['chickpeas'], PackageType::Can, '400 g can', '400', UnitCode::Gram, '400'],
            'tomato_can' => [$ingredients['tomatoes'], PackageType::Can, '400 g can', '400', UnitCode::Gram, '400'],
            'coconut_can' => [$ingredients['coconut_milk'], PackageType::Can, '400 ml can', '400', UnitCode::Millilitre, '400'],
            'spinach_bag' => [$ingredients['spinach'], PackageType::Bag, '200 g bag', '200', UnitCode::Gram, '200'],
            'rice_bag' => [$ingredients['rice'], PackageType::Bag, '1 kg bag', '1000', UnitCode::Gram, '1000'],
            'oil_bottle' => [$ingredients['olive_oil'], PackageType::Bottle, '500 ml bottle', '500', UnitCode::Millilitre, '500'],
            'milk_bottle' => [$ingredients['milk'], PackageType::Bottle, '1 l bottle', '1000', UnitCode::Millilitre, '1000'],
            'tuna_can' => [$ingredients['tuna'], PackageType::Can, '145 g can', '145', UnitCode::Gram, '145'],
            'egg_pack' => [$ingredients['eggs'], PackageType::Pack, 'Mixed-size egg pack', null, null, null],
            'bread_bag' => [$ingredients['bread'], PackageType::Bag, 'Bakery loaf', null, null, null],
        ];
        $packages = [];

        foreach ($definitions as $key => [$ingredient, $type, $label, $amount, $unit, $normalizedAmount]) {
            $packages[$key] = IngredientPackage::query()->updateOrCreate(
                ['ingredient_id' => $ingredient->id, 'label' => $label],
                [
                    'package_type' => $type,
                    'content_amount' => $amount,
                    'content_unit' => $unit,
                    'normalized_content_amount' => $normalizedAmount,
                ],
            );
        }

        return $packages;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, IngredientPackage>  $packages
     */
    private function seedPantry(User $user, array $ingredients, array $packages): void
    {
        $entries = [
            [$ingredients['spaghetti'], null, UnitCode::Gram, '500', 'mass', 'direct:mass'],
            [$ingredients['rice'], null, UnitCode::Gram, '150', 'mass', 'direct:mass'],
            [$ingredients['chickpeas'], $packages['chickpea_can'], null, '800', 'mass', 'package:'.$packages['chickpea_can']->id],
            [$ingredients['tomatoes'], $packages['tomato_can'], null, '400', 'mass', 'package:'.$packages['tomato_can']->id],
            [$ingredients['garlic'], null, UnitCode::Clove, '8', 'count:'.$ingredients['garlic']->id.':clove', 'direct:count:'.$ingredients['garlic']->id.':clove'],
            [$ingredients['eggs'], null, UnitCode::Piece, '6', 'count:'.$ingredients['eggs']->id.':piece', 'direct:count:'.$ingredients['eggs']->id.':piece'],
            [$ingredients['milk'], null, UnitCode::Millilitre, '500', 'volume', 'direct:volume'],
            [$ingredients['spinach'], $packages['spinach_bag'], null, '200', 'mass', 'package:'.$packages['spinach_bag']->id],
            [$ingredients['parmesan'], null, UnitCode::Gram, '50', 'mass', 'direct:mass'],
            [$ingredients['black_pepper'], null, UnitCode::Gram, '25', 'mass', 'direct:mass'],
        ];

        foreach ($entries as [$ingredient, $package, $displayUnit, $amount, $compatibilityKey, $mergeKey]) {
            $user->pantryEntries()->firstOrCreate(
                ['ingredient_id' => $ingredient->id, 'merge_key' => $mergeKey],
                [
                    'ingredient_package_id' => $package?->id,
                    'display_unit' => $displayUnit,
                    'total_normalized_amount' => $amount,
                    'compatibility_key' => $compatibilityKey,
                ],
            );
        }
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, IngredientPackage>  $packages
     */
    private function seedRecipes(User $user, array $ingredients, array $packages): void
    {
        $categories = $this->seedCategories($user, ['Quick meals', 'Weeknight dinners', 'Vegetarian']);
        $tags = $this->seedTags($user, ['Pantry ready', 'Partial pantry', 'Missing ingredients', 'High protein']);

        $this->seedRecipe($user, [
            'name' => 'Spaghetti Aglio e Olio',
            'description' => 'A fast pantry dinner that should rank highly with the seeded stock.',
            'default_servings' => 4,
            'preparation_minutes' => 10,
            'cooking_minutes' => 15,
            'difficulty' => 'Easy',
            'cuisine' => 'Italian',
            'meal_type' => 'Dinner',
            'notes' => 'Salt is optional and excluded from recommendation scoring.',
            'source_url' => 'https://example.com/spaghetti-aglio-e-olio',
            'archived_at' => null,
        ], [
            $this->exactIngredient($ingredients['spaghetti'], '400', UnitCode::Gram, '400', 'mass'),
            $this->exactIngredient($ingredients['garlic'], '4', UnitCode::Clove, '4', 'count:'.$ingredients['garlic']->id.':clove'),
            $this->exactIngredient($ingredients['olive_oil'], '60', UnitCode::Millilitre, '60', 'volume'),
            $this->nonExactIngredient($ingredients['salt'], 'To taste', NonExactStatus::Optional),
            $this->nonExactIngredient($ingredients['black_pepper'], 'Freshly ground, to taste', NonExactStatus::Optional),
        ], [
            'Boil the spaghetti until al dente.',
            'Gently cook the garlic in olive oil.',
            'Toss together with pasta water and season.',
        ], [$categories['Quick meals'], $categories['Weeknight dinners']], [$tags['Pantry ready']]);

        $this->seedRecipe($user, [
            'name' => 'Chickpea Tomato Curry',
            'description' => 'A package-aware recipe with one deliberately missing ingredient.',
            'default_servings' => 4,
            'preparation_minutes' => 10,
            'cooking_minutes' => 30,
            'difficulty' => 'Easy',
            'cuisine' => 'Indian inspired',
            'meal_type' => 'Dinner',
            'notes' => null,
            'source_url' => null,
            'archived_at' => null,
        ], [
            $this->packageIngredient($ingredients['chickpeas'], $packages['chickpea_can'], '2', '800', 'mass'),
            $this->packageIngredient($ingredients['tomatoes'], $packages['tomato_can'], '1', '400', 'mass'),
            $this->packageIngredient($ingredients['coconut_milk'], $packages['coconut_can'], '1', '400', 'volume'),
            $this->exactIngredient($ingredients['onion'], '1', UnitCode::Piece, '1', 'count:'.$ingredients['onion']->id.':piece'),
            $this->exactIngredient($ingredients['garlic'], '3', UnitCode::Clove, '3', 'count:'.$ingredients['garlic']->id.':clove'),
        ], [
            'Soften the onion and garlic.',
            'Add tomatoes, chickpeas, and coconut milk.',
            'Simmer until thickened and season.',
        ], [$categories['Weeknight dinners'], $categories['Vegetarian']], [$tags['Partial pantry']]);

        $this->seedRecipe($user, [
            'name' => 'Spinach Fried Rice',
            'description' => 'Demonstrates partial rice coverage alongside covered count and package ingredients.',
            'default_servings' => 4,
            'preparation_minutes' => 15,
            'cooking_minutes' => 15,
            'difficulty' => 'Medium',
            'cuisine' => 'Asian inspired',
            'meal_type' => 'Dinner',
            'notes' => null,
            'source_url' => null,
            'archived_at' => null,
        ], [
            $this->exactIngredient($ingredients['rice'], '300', UnitCode::Gram, '300', 'mass'),
            $this->exactIngredient($ingredients['eggs'], '3', UnitCode::Piece, '3', 'count:'.$ingredients['eggs']->id.':piece'),
            $this->packageIngredient($ingredients['spinach'], $packages['spinach_bag'], '1', '200', 'mass'),
            $this->exactIngredient($ingredients['onion'], '1', UnitCode::Piece, '1', 'count:'.$ingredients['onion']->id.':piece'),
        ], [
            'Cook and cool the rice if needed.',
            'Scramble the eggs and set aside.',
            'Stir-fry everything together until hot.',
        ], [$categories['Quick meals'], $categories['Vegetarian']], [$tags['Partial pantry'], $tags['High protein']]);

        $this->seedRecipe($user, [
            'name' => 'Creamy Chicken Pasta',
            'description' => 'A low-ranking recipe with missing chicken and partially covered cheese.',
            'default_servings' => 4,
            'preparation_minutes' => 15,
            'cooking_minutes' => 25,
            'difficulty' => 'Medium',
            'cuisine' => 'European',
            'meal_type' => 'Dinner',
            'notes' => null,
            'source_url' => null,
            'archived_at' => null,
        ], [
            $this->exactIngredient($ingredients['spaghetti'], '300', UnitCode::Gram, '300', 'mass'),
            $this->exactIngredient($ingredients['chicken'], '500', UnitCode::Gram, '500', 'mass'),
            $this->exactIngredient($ingredients['milk'], '250', UnitCode::Millilitre, '250', 'volume'),
            $this->exactIngredient($ingredients['parmesan'], '100', UnitCode::Gram, '100', 'mass'),
            $this->nonExactIngredient($ingredients['black_pepper'], 'Generously, to finish', NonExactStatus::Required),
        ], [
            'Cook the pasta and reserve some cooking water.',
            'Cook the chicken thoroughly.',
            'Make the sauce, combine, and finish with parmesan.',
        ], [$categories['Weeknight dinners']], [$tags['Missing ingredients'], $tags['High protein']]);

        $this->seedRecipe($user, [
            'name' => 'Spinach Omelette',
            'description' => 'A short recipe with covered pantry quantities and a staple seasoning.',
            'default_servings' => 2,
            'preparation_minutes' => 5,
            'cooking_minutes' => 10,
            'difficulty' => 'Easy',
            'cuisine' => null,
            'meal_type' => 'Breakfast',
            'notes' => null,
            'source_url' => null,
            'archived_at' => null,
        ], [
            $this->exactIngredient($ingredients['eggs'], '4', UnitCode::Piece, '4', 'count:'.$ingredients['eggs']->id.':piece'),
            $this->exactIngredient($ingredients['spinach'], '100', UnitCode::Gram, '100', 'mass'),
            $this->exactIngredient($ingredients['milk'], '50', UnitCode::Millilitre, '50', 'volume'),
            $this->nonExactIngredient($ingredients['salt'], 'A pinch', NonExactStatus::Optional),
        ], [
            'Whisk the eggs with milk and salt.',
            'Wilt the spinach in the pan.',
            'Add the eggs and cook until just set.',
        ], [$categories['Quick meals'], $categories['Vegetarian']], [$tags['Pantry ready'], $tags['High protein']]);

        $this->seedRecipe($user, [
            'name' => 'Butter Toast',
            'description' => null,
            'default_servings' => 1,
            'preparation_minutes' => null,
            'cooking_minutes' => null,
            'difficulty' => null,
            'cuisine' => null,
            'meal_type' => null,
            'notes' => null,
            'source_url' => null,
            'archived_at' => null,
        ], [
            $this->exactIngredient($ingredients['bread'], '2', UnitCode::Slice, '2', 'count:'.$ingredients['bread']->id.':slice'),
        ], [
            'Toast the bread until golden.',
        ], [], []);

        $this->seedRecipe($user, [
            'name' => 'Rosemary Roast Vegetables',
            'description' => 'Count- and mass-aware vegetables with a required herb garnish.',
            'default_servings' => 4,
            'preparation_minutes' => 15,
            'cooking_minutes' => 40,
            'difficulty' => 'Easy',
            'cuisine' => 'European',
            'meal_type' => 'Dinner',
            'notes' => null,
            'source_url' => null,
            'archived_at' => null,
        ], [
            $this->exactIngredient($ingredients['potatoes'], '800', UnitCode::Gram, '800', 'mass'),
            $this->exactIngredient($ingredients['carrot'], '4', UnitCode::Piece, '4', 'count:'.$ingredients['carrot']->id.':piece'),
            $this->exactIngredient($ingredients['bell_pepper'], '2', UnitCode::Piece, '2', 'count:'.$ingredients['bell_pepper']->id.':piece'),
            $this->exactIngredient($ingredients['rosemary'], '2', UnitCode::Sprig, '2', 'count:'.$ingredients['rosemary']->id.':sprig'),
        ], [
            'Cut the vegetables into even pieces.',
            'Roast with rosemary until tender and browned.',
        ], [$categories['Weeknight dinners'], $categories['Vegetarian']], [$tags['Missing ingredients']]);

        $this->seedRecipe($user, [
            'name' => 'Garlic Bulb Soup',
            'description' => 'Keeps bulb counts distinct from clove counts.',
            'default_servings' => 4,
            'preparation_minutes' => 10,
            'cooking_minutes' => 45,
            'difficulty' => 'Easy',
            'cuisine' => 'European',
            'meal_type' => 'Dinner',
            'notes' => null,
            'source_url' => null,
            'archived_at' => null,
        ], [
            $this->exactIngredient($ingredients['garlic_bulb'], '2', UnitCode::Bulb, '2', 'count:'.$ingredients['garlic_bulb']->id.':bulb'),
            $this->exactIngredient($ingredients['potatoes'], '400', UnitCode::Gram, '400', 'mass'),
            $this->nonExactIngredient($ingredients['salt'], 'To taste', NonExactStatus::Required),
        ], [
            'Roast the garlic bulbs until soft.',
            'Simmer with potatoes, then blend until smooth.',
        ], [$categories['Vegetarian']], [$tags['Missing ingredients']]);

        $this->seedRecipe($user, [
            'name' => 'Tuna Rice Bowl',
            'description' => 'A known package example with an exact metric grocery shortfall.',
            'default_servings' => 2,
            'preparation_minutes' => 10,
            'cooking_minutes' => 20,
            'difficulty' => 'Easy',
            'cuisine' => 'International',
            'meal_type' => 'Dinner',
            'notes' => null,
            'source_url' => null,
            'archived_at' => null,
        ], [
            $this->exactIngredient($ingredients['rice'], '160', UnitCode::Gram, '160', 'mass'),
            $this->packageIngredient($ingredients['tuna'], $packages['tuna_can'], '1', '145', 'mass'),
            $this->exactIngredient($ingredients['lemon'], '1', UnitCode::Piece, '1', 'count:'.$ingredients['lemon']->id.':piece'),
        ], [
            'Cook the rice.',
            'Top with tuna and fresh lemon.',
        ], [$categories['Quick meals']], [$tags['Partial pantry'], $tags['High protein']]);

        $this->seedRecipe($user, [
            'name' => 'Yoghurt Oats',
            'description' => 'A simple mass-based breakfast with an optional garnish.',
            'default_servings' => 2,
            'preparation_minutes' => 5,
            'cooking_minutes' => 0,
            'difficulty' => 'Easy',
            'cuisine' => null,
            'meal_type' => 'Breakfast',
            'notes' => null,
            'source_url' => null,
            'archived_at' => null,
        ], [
            $this->exactIngredient($ingredients['oats'], '100', UnitCode::Gram, '100', 'mass'),
            $this->exactIngredient($ingredients['yoghurt'], '300', UnitCode::Gram, '300', 'mass'),
            $this->nonExactIngredient($ingredients['basil'], 'Optional garnish', NonExactStatus::Optional),
        ], [
            'Mix the oats and yoghurt, then rest before serving.',
        ], [$categories['Quick meals'], $categories['Vegetarian']], [$tags['Missing ingredients']]);

        $this->seedRecipe($user, [
            'name' => 'Archived Parmesan Pasta',
            'description' => 'An archived fixture for testing restore and archive views.',
            'default_servings' => 2,
            'preparation_minutes' => 5,
            'cooking_minutes' => 15,
            'difficulty' => 'Easy',
            'cuisine' => 'Italian',
            'meal_type' => 'Dinner',
            'notes' => 'This recipe should not appear in recommendations.',
            'source_url' => null,
            'archived_at' => now(),
        ], [
            $this->exactIngredient($ingredients['spaghetti'], '200', UnitCode::Gram, '200', 'mass'),
            $this->exactIngredient($ingredients['parmesan'], '80', UnitCode::Gram, '80', 'mass'),
        ], [
            'Cook the spaghetti.',
            'Toss with parmesan and pasta water.',
        ], [$categories['Quick meals']], [$tags['Partial pantry']]);
    }

    /**
     * @param  list<string>  $names
     * @return array<string, RecipeCategory>
     */
    private function seedCategories(User $user, array $names): array
    {
        $items = [];

        foreach ($names as $name) {
            $items[$name] = RecipeCategory::query()->updateOrCreate(
                ['user_id' => $user->id, 'normalized_name' => Str::lower($name)],
                ['name' => $name],
            );
        }

        return $items;
    }

    /**
     * @param  list<string>  $names
     * @return array<string, Tag>
     */
    private function seedTags(User $user, array $names): array
    {
        $items = [];

        foreach ($names as $name) {
            $items[$name] = Tag::query()->updateOrCreate(
                ['user_id' => $user->id, 'normalized_name' => Str::lower($name)],
                ['name' => $name],
            );
        }

        return $items;
    }

    /**
     * @param  array{name: string, description: string|null, default_servings: int, preparation_minutes: int|null, cooking_minutes: int|null, difficulty: string|null, cuisine: string|null, meal_type: string|null, notes: string|null, source_url: string|null, archived_at: mixed}  $attributes
     * @param  list<array<string, mixed>>  $ingredients
     * @param  list<string>  $steps
     * @param  list<RecipeCategory>  $categories
     * @param  list<Tag>  $tags
     */
    private function seedRecipe(User $user, array $attributes, array $ingredients, array $steps, array $categories, array $tags): void
    {
        $recipe = Recipe::query()->updateOrCreate(
            ['user_id' => $user->id, 'name' => $attributes['name']],
            $attributes,
        );

        $recipe->ingredients()->delete();
        foreach ($ingredients as $position => $ingredient) {
            $recipe->ingredients()->create([...$ingredient, 'position' => $position + 1]);
        }

        $recipe->steps()->delete();
        foreach ($steps as $position => $instruction) {
            $recipe->steps()->create(['instruction' => $instruction, 'position' => $position + 1]);
        }

        $recipe->categories()->sync(collect($categories)->pluck('id'));
        $recipe->tags()->sync(collect($tags)->pluck('id'));
    }

    /** @return array<string, mixed> */
    private function exactIngredient(Ingredient $ingredient, string $amount, UnitCode $unit, string $normalizedAmount, string $compatibilityKey): array
    {
        return [
            'ingredient_id' => $ingredient->id,
            'ingredient_package_id' => null,
            'quantity_type' => QuantityType::Exact,
            'entered_amount' => $amount,
            'entered_unit' => $unit,
            'normalized_amount' => $normalizedAmount,
            'compatibility_key' => $compatibilityKey,
            'quantity_description' => null,
            'non_exact_status' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function packageIngredient(Ingredient $ingredient, IngredientPackage $package, string $amount, string $normalizedAmount, string $compatibilityKey): array
    {
        return [
            'ingredient_id' => $ingredient->id,
            'ingredient_package_id' => $package->id,
            'quantity_type' => QuantityType::Exact,
            'entered_amount' => $amount,
            'entered_unit' => null,
            'normalized_amount' => $normalizedAmount,
            'compatibility_key' => $compatibilityKey,
            'quantity_description' => null,
            'non_exact_status' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function nonExactIngredient(Ingredient $ingredient, string $description, NonExactStatus $status): array
    {
        return [
            'ingredient_id' => $ingredient->id,
            'ingredient_package_id' => null,
            'quantity_type' => QuantityType::NonExact,
            'entered_amount' => null,
            'entered_unit' => null,
            'normalized_amount' => null,
            'compatibility_key' => null,
            'quantity_description' => $description,
            'non_exact_status' => $status,
        ];
    }
}
