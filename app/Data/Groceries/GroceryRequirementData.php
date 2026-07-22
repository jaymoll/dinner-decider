<?php

namespace App\Data\Groceries;

use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\RequirementCoverage;
use App\Enums\UnitCode;

final readonly class GroceryRequirementData
{
    /** @param numeric-string|null $missingAmount */
    public function __construct(
        public int $requirementId,
        public int $ingredientId,
        public string $ingredientName,
        public ?string $ingredientCategory,
        public QuantityType $quantityType,
        public ?NonExactStatus $nonExactStatus,
        public RequirementCoverage $coverage,
        public ?string $missingAmount,
        public ?string $compatibilityKey,
        public ?string $quantityDescription,
        public ?int $ingredientPackageId,
        public ?string $packageLabel,
        public ?UnitCode $packageContentUnit,
    ) {}
}
