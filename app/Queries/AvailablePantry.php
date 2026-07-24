<?php

namespace App\Queries;

use App\Data\Pantry\PantryAvailability;
use App\Data\Pantry\PantryBalance;
use App\Data\Pantry\PantryBucket;
use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\User;
use App\Services\Measurements\QuantityFormatter;
use App\ValueObjects\CompatibilityKey;

/**
 * Builds the authoritative available-stock view from totals, reservations, and staple policy.
 */
final class AvailablePantry
{
    public function __construct(private readonly QuantityFormatter $formatter) {}

    public function get(User $user, bool $includeReservationDetails = false): PantryAvailability
    {
        $entries = PantryEntry::query()
            ->whereBelongsTo($user)
            ->with(['ingredient', 'ingredientPackage'])
            ->when($includeReservationDetails, fn ($query) => $query->with('reservations.requirement.plannedDinner'))
            ->withSum('reservations as reserved_normalized_amount', 'normalized_amount')
            ->oldest('id')
            ->get();

        $balances = $entries->map(function (PantryEntry $entry): PantryBalance {
            $reserved = $this->reservedAmount($entry);

            // Temporary unavailability masks stock from decisions without destroying its balance.
            $available = $entry->ingredient->is_currently_available
                ? bcsub($entry->total_normalized_amount, $reserved, $this->scale())
                : '0';

            return new PantryBalance(
                $entry,
                $entry->total_normalized_amount,
                $reserved,
                $available,
                $this->display($entry, $entry->total_normalized_amount, true),
                $this->display($entry, $reserved),
                $this->display($entry, $available),
            );
        });

        // Allocation and recommendation consumers operate on compatibility buckets, not on the
        // package or display-unit rows that happen to hold the stock.
        $bucketAmounts = [];
        foreach ($balances as $balance) {
            $key = $balance->entry->ingredient_id.'|'.$balance->entry->compatibility_key;
            $bucketAmounts[$key] = isset($bucketAmounts[$key])
                ? bcadd($bucketAmounts[$key], $balance->availableAmount, $this->scale())
                : $balance->availableAmount;
        }

        $buckets = collect($bucketAmounts)->map(function (string $amount, string $key): PantryBucket {
            [$ingredientId, $compatibilityKey] = explode('|', $key, 2);

            return new PantryBucket((int) $ingredientId, $compatibilityKey, $amount);
        });

        Ingredient::query()->whereBelongsTo($user)->active()
            ->where('is_staple', true)->where('is_currently_available', true)
            ->get(['id', 'preferred_unit'])
            ->each(function (Ingredient $ingredient) use ($buckets): void {
                // An available staple is represented as an unlimited bucket even with no entry.
                $compatibilityKey = (string) CompatibilityKey::forUnit($ingredient->preferred_unit, $ingredient->id);
                $bucket = new PantryBucket($ingredient->id, $compatibilityKey, '0', true);
                $buckets->put($bucket->key(), $bucket);
            });

        return new PantryAvailability($balances, $buckets);
    }

    /** @return numeric-string */
    private function reservedAmount(PantryEntry $entry): string
    {
        return (string) ($entry->reserved_normalized_amount ?? '0');
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }

    /** @param numeric-string $amount */
    private function display(PantryEntry $entry, string $amount, bool $includePackageContext = false): string
    {
        $package = $entry->ingredientPackage;
        if ($package !== null && ! $package->hasKnownContents()) {
            return $this->formatter->formatAmount($amount, true).' '.$package->label;
        }

        if ($package !== null && $includePackageContext) {
            // Known packages retain their shopping context while also exposing the metric truth.
            $count = bcdiv($amount, (string) $package->normalized_content_amount, $this->scale());

            return $this->formatter->formatAmount($count, true).' × '.$package->label.' — '.$this->metricDisplay($entry, $amount).' total';
        }

        if ($package === null && $entry->display_unit !== null) {
            $displayAmount = bcdiv($amount, $entry->display_unit->factorToBase(), $this->scale());

            return $this->formatter->formatAmount($displayAmount, $entry->display_unit->measurementGroup()->value === 'count').' '.$entry->display_unit->value;
        }

        return $this->metricDisplay($entry, $amount);
    }

    /** @param numeric-string $amount */
    private function metricDisplay(PantryEntry $entry, string $amount): string
    {
        if ($entry->compatibility_key === 'mass' || $entry->compatibility_key === 'volume') {
            $large = bccomp($amount, '1000', $this->scale()) >= 0;
            $displayAmount = $large ? bcdiv($amount, '1000', $this->scale()) : $amount;
            $unit = $entry->compatibility_key === 'mass' ? ($large ? 'kg' : 'g') : ($large ? 'l' : 'ml');

            return $this->formatter->formatAmount($displayAmount).' '.$unit;
        }

        $unit = $entry->display_unit !== null ? $entry->display_unit->value : 'packages';

        return $this->formatter->formatAmount($amount, true).' '.$unit;
    }
}
