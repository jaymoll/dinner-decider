<?php

namespace App\Services\DinnerPlans;

use App\Data\DinnerPlans\Allocation;

final class PantryAllocator
{
    /**
     * @param  numeric-string  $requiredAmount
     * @param  list<array{id: int, available_amount: numeric-string, native: bool}>  $entries
     * @return list<Allocation>
     */
    public function allocate(string $requiredAmount, array $entries): array
    {
        usort($entries, fn (array $left, array $right): int => [$right['native'], $left['id']] <=> [$left['native'], $right['id']]);

        $remaining = $requiredAmount;
        $allocations = [];

        foreach ($entries as $entry) {
            if (bccomp($remaining, '0', $this->scale()) <= 0) {
                break;
            }

            if (bccomp($entry['available_amount'], '0', $this->scale()) <= 0) {
                continue;
            }

            $amount = bccomp($entry['available_amount'], $remaining, $this->scale()) <= 0
                ? $entry['available_amount']
                : $remaining;
            $allocations[] = new Allocation($entry['id'], $amount);
            $remaining = bcsub($remaining, $amount, $this->scale());
        }

        return $allocations;
    }

    private function scale(): int
    {
        return 6;
    }
}
