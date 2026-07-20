<?php

namespace App\Exceptions;

use RuntimeException;

class PantryEntryRemovalRequiresConfirmation extends RuntimeException
{
    /** @param array<int, array{dinner: string, date: string|null, amount: string}> $reservations */
    public function __construct(public readonly array $reservations)
    {
        parent::__construct('This pantry entry has active dinner reservations.');
    }
}
