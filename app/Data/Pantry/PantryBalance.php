<?php

namespace App\Data\Pantry;

use App\Models\PantryEntry;

final readonly class PantryBalance
{
    /** @var numeric-string */
    public string $totalAmount;

    /** @var numeric-string */
    public string $reservedAmount;

    /** @var numeric-string */
    public string $availableAmount;

    /**
     * @param  numeric-string  $totalAmount
     * @param  numeric-string  $reservedAmount
     * @param  numeric-string  $availableAmount
     */
    public function __construct(
        public PantryEntry $entry,
        string $totalAmount,
        string $reservedAmount,
        string $availableAmount,
        public string $totalDisplay,
        public string $reservedDisplay,
        public string $availableDisplay,
    ) {
        $this->totalAmount = $totalAmount;
        $this->reservedAmount = $reservedAmount;
        $this->availableAmount = $availableAmount;
    }
}
