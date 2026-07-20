<?php

namespace App\Services\Measurements;

use App\Enums\MeasurementGroup;
use App\ValueObjects\Quantity;

final class QuantityFormatter
{
    public function format(Quantity $quantity, ?string $packageLabel = null): string
    {
        $amount = $this->formatAmount($quantity->amount, in_array(
            $quantity->measurementGroup,
            [MeasurementGroup::Count, MeasurementGroup::Package],
            true,
        ));

        if ($quantity->unit !== null) {
            return "{$amount} {$quantity->unit->value}";
        }

        return $packageLabel !== null ? "{$amount} {$packageLabel}" : "{$amount} packages";
    }

    public function formatNormalized(Quantity $quantity): string
    {
        $amount = $this->formatAmount($quantity->normalizedAmount);
        $unit = match ($quantity->measurementGroup) {
            MeasurementGroup::Mass => 'g',
            MeasurementGroup::Volume => 'ml',
            MeasurementGroup::Count => $quantity->unit->value,
            MeasurementGroup::Package => 'packages',
        };

        return "{$amount} {$unit}";
    }

    /** @param  numeric-string  $value */
    public function formatAmount(string $value, bool $useFractions = false): string
    {
        $rounded = $this->roundHalfUp($value, (int) config('measurements.display_scale', 2));

        if ($useFractions) {
            $fraction = $this->asFraction($rounded);

            if ($fraction !== null) {
                return $fraction;
            }
        }

        return $this->canonical($rounded);
    }

    /**
     * @param  numeric-string  $value
     * @return numeric-string
     */
    private function roundHalfUp(string $value, int $scale): string
    {
        $increment = match ($scale) {
            0 => '0.5',
            1 => '0.05',
            2 => '0.005',
            3 => '0.0005',
            4 => '0.00005',
            5 => '0.000005',
            6 => '0.0000005',
            default => throw new \InvalidArgumentException('Display scale must be between zero and six.'),
        };

        return bcdiv(bcadd($value, $increment, $scale + 1), '1', $scale);
    }

    /** @param  numeric-string  $value */
    private function asFraction(string $value): ?string
    {
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        $decimal = rtrim($decimal, '0');

        if ($decimal === '') {
            return $whole;
        }

        $decimalValue = '0.'.$decimal;
        $fractions = config('measurements.fractions', []);
        $glyph = is_array($fractions) ? ($fractions[$decimalValue] ?? null) : null;

        if (! is_string($glyph)) {
            return null;
        }

        return $whole === '0' ? $glyph : $whole.$glyph;
    }

    /**
     * @param  numeric-string  $value
     * @return numeric-string
     */
    private function canonical(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('The formatted quantity is not numeric.');
        }

        return $value;
    }
}
