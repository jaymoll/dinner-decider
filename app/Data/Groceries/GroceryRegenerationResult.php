<?php

namespace App\Data\Groceries;

final readonly class GroceryRegenerationResult
{
    /**
     * @param  list<int>  $increasedItemIds
     * @param  list<int>  $clearedOverrideItemIds
     */
    public function __construct(
        public array $increasedItemIds = [],
        public array $clearedOverrideItemIds = [],
    ) {}
}
