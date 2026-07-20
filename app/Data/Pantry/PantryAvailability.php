<?php

namespace App\Data\Pantry;

use Illuminate\Support\Collection;

final readonly class PantryAvailability
{
    /**
     * @param  Collection<int, PantryBalance>  $balances
     * @param  Collection<string, PantryBucket>  $buckets
     */
    public function __construct(
        public Collection $balances,
        public Collection $buckets,
    ) {}
}
