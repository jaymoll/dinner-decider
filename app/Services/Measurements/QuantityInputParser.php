<?php

namespace App\Services\Measurements;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class QuantityInputParser
{
    /** @var array<string, numeric-string> */
    private const UNICODE_FRACTIONS = [
        '⅛' => '0.125',
        '¼' => '0.25',
        '⅜' => '0.375',
        '½' => '0.5',
        '⅝' => '0.625',
        '¾' => '0.75',
        '⅞' => '0.875',
        '⅓' => '0.333333',
        '⅔' => '0.666667',
    ];

    /** @return numeric-string */
    public function parse(string $input): string
    {
        $value = Str::of($input)->trim()->squish()->toString();

        if ($value === '') {
            throw new InvalidArgumentException('Enter a quantity.');
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            throw new InvalidArgumentException('Thousands separators are not supported.');
        }

        $value = str_replace(',', '.', $value);
        $parsed = $this->parseUnicodeFraction($value)
            ?? $this->parseMixedFraction($value)
            ?? $this->parseSimpleFraction($value)
            ?? $this->parseDecimal($value);

        if (bccomp($parsed, '0', $this->scale()) <= 0) {
            throw new InvalidArgumentException('Quantities must be greater than zero.');
        }

        return $this->canonical($parsed);
    }

    /** @return numeric-string|null */
    private function parseUnicodeFraction(string $value): ?string
    {
        foreach (self::UNICODE_FRACTIONS as $glyph => $fraction) {
            if (! str_contains($value, $glyph)) {
                continue;
            }

            $whole = trim(str_replace($glyph, '', $value));

            if ($whole !== '' && ! preg_match('/^\d+$/', $whole)) {
                throw new InvalidArgumentException('The fraction is not in a supported format.');
            }

            if ($whole !== '' && ! is_numeric($whole)) {
                throw new InvalidArgumentException('The whole-number part is not numeric.');
            }

            return bcadd($whole === '' ? '0' : $whole, $fraction, $this->scale());
        }

        return null;
    }

    /** @return numeric-string|null */
    private function parseMixedFraction(string $value): ?string
    {
        if (! preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $value, $matches)) {
            return null;
        }

        $whole = $this->numericMatch($matches[1]);
        $numerator = $this->numericMatch($matches[2]);
        $denominator = $this->numericMatch($matches[3]);

        return bcadd($whole, $this->divide($numerator, $denominator), $this->scale());
    }

    /** @return numeric-string|null */
    private function parseSimpleFraction(string $value): ?string
    {
        if (! preg_match('/^(\d+)\/(\d+)$/', $value, $matches)) {
            return null;
        }

        return $this->divide($this->numericMatch($matches[1]), $this->numericMatch($matches[2]));
    }

    /** @return numeric-string */
    private function parseDecimal(string $value): string
    {
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Enter a decimal or supported fraction.');
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('The quantity is not numeric.');
        }

        $decimalPlaces = str_contains($value, '.') ? Str::length(Str::after($value, '.')) : 0;

        if ($decimalPlaces > $this->scale()) {
            throw new InvalidArgumentException("Quantities may have at most {$this->scale()} decimal places.");
        }

        return $value;
    }

    /**
     * @param  numeric-string  $numerator
     * @param  numeric-string  $denominator
     * @return numeric-string
     */
    private function divide(string $numerator, string $denominator): string
    {
        if (bccomp($denominator, '0', $this->scale()) === 0) {
            throw new InvalidArgumentException('A fraction cannot divide by zero.');
        }

        return bcdiv($numerator, $denominator, $this->scale());
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

        $value = $value === '' ? '0' : $value;

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('The quantity is not numeric.');
        }

        return $value;
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }

    /** @return numeric-string */
    private function numericMatch(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('The fraction contains a non-numeric value.');
        }

        return $value;
    }
}
