<?php

namespace App\Data\Groceries;

use App\Enums\GroceryCategory;
use App\Enums\UnitCode;

final readonly class GroceryCalculationItem
{
    /**
     * @param  numeric-string|null  $calculatedAmount
     * @param  list<GroceryContributionData>  $contributions
     */
    public function __construct(
        public string $generationKey,
        public int $ingredientId,
        public string $name,
        public GroceryCategory $category,
        public ?string $calculatedAmount,
        public ?UnitCode $calculatedUnit,
        public ?string $quantityDescription,
        public ?int $ingredientPackageId,
        public ?string $packageLabel,
        public array $contributions,
    ) {}
}
