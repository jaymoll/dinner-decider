<?php

return [
    // Domain arithmetic retains six decimal places; rounding is deferred to the display boundary.
    'calculation_scale' => 6,
    'display_scale' => 2,
    'rounding_mode' => PHP_ROUND_HALF_UP,

    // Only exact decimal matches are rendered as glyphs so display never implies false precision.
    'fractions' => [
        '0.125' => '⅛',
        '0.25' => '¼',
        '0.375' => '⅜',
        '0.5' => '½',
        '0.625' => '⅝',
        '0.75' => '¾',
        '0.875' => '⅞',
    ],
    // Shared input limits keep Livewire validation and storage services aligned.
    'limits' => [
        'aliases_per_ingredient' => 20,
        'packages_per_ingredient' => 20,
        'ingredients_per_recipe' => 100,
        'steps_per_recipe' => 100,
        'categories_per_recipe' => 20,
        'tags_per_recipe' => 30,
        'recipe_image_kilobytes' => 4096,
        'recipe_image_dimension_pixels' => 6000,
    ],
];
