<?php

namespace App\Data\Pantry;

final readonly class PantryBucket
{
    /** @var numeric-string */
    public string $availableAmount;

    /** @param numeric-string $availableAmount */
    public function __construct(
        public int $ingredientId,
        public string $compatibilityKey,
        string $availableAmount,
        public bool $unlimited = false,
    ) {
        $this->availableAmount = $availableAmount;
    }

    public function key(): string
    {
        return $this->ingredientId.'|'.$this->compatibilityKey;
    }
}
