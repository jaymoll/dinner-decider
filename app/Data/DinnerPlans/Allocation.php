<?php

namespace App\Data\DinnerPlans;

final readonly class Allocation
{
    /** @var numeric-string */
    public string $normalizedAmount;

    /** @param numeric-string $normalizedAmount */
    public function __construct(public int $pantryEntryId, string $normalizedAmount)
    {
        $this->normalizedAmount = $normalizedAmount;
    }
}
