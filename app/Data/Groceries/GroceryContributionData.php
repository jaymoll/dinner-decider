<?php

namespace App\Data\Groceries;

final readonly class GroceryContributionData
{
    /** @param numeric-string|null $normalizedAmount */
    public function __construct(
        public int $requirementId,
        public ?string $normalizedAmount,
    ) {}
}
