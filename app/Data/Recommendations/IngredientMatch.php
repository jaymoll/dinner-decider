<?php

namespace App\Data\Recommendations;

final readonly class IngredientMatch
{
    /**
     * @param  numeric-string|null  $requiredAmount
     * @param  numeric-string|null  $availableAmount
     * @param  numeric-string|null  $missingAmount
     */
    public function __construct(
        public int $ingredientId,
        public string $ingredientName,
        public string $status,
        public bool $exact,
        public ?string $requiredAmount = null,
        public ?string $availableAmount = null,
        public ?string $missingAmount = null,
        public ?string $compatibilityKey = null,
        public ?string $description = null,
        public ?string $unitLabel = null,
        public ?string $nonExactStatus = null,
    ) {}
}
