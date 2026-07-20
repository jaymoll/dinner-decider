<?php

namespace App\Data\Recommendations;

use App\Models\Recipe;

final readonly class RecommendationResult
{
    /**
     * @param  numeric-string  $servings
     * @param  numeric-string  $score
     * @param  numeric-string  $quantityCoverage
     * @param  list<IngredientMatch>  $matches
     */
    public function __construct(
        public Recipe $recipe,
        public string $servings,
        public string $score,
        public string $quantityCoverage,
        public array $matches,
        public int $fullCount,
        public int $partialCount,
        public int $missingCount,
        public int $incompatibleCount,
        public int $exactCount,
    ) {}
}
