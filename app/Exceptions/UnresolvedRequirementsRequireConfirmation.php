<?php

namespace App\Exceptions;

use RuntimeException;

class UnresolvedRequirementsRequireConfirmation extends RuntimeException
{
    /**
     * @param  list<array<string, mixed>>  $summary
     */
    public function __construct(public readonly array $summary, public readonly string $fingerprint)
    {
        parent::__construct('Unresolved dinner requirements require confirmation.');
    }
}
