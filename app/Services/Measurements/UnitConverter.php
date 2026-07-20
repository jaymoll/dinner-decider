<?php

namespace App\Services\Measurements;

use App\Data\Measurements\QuantityInput;
use App\Enums\MeasurementGroup;
use App\ValueObjects\CompatibilityKey;
use App\ValueObjects\Quantity;
use InvalidArgumentException;

final readonly class UnitConverter
{
    public function __construct(private QuantityInputParser $parser) {}

    public function normalize(QuantityInput $input): Quantity
    {
        $amount = $this->parser->parse($input->amount);

        if ($input->isPackage()) {
            return $this->normalizePackage($input, $amount);
        }

        if ($input->unit === null) {
            throw new InvalidArgumentException('A direct quantity requires a unit.');
        }

        $normalizedAmount = bcmul($amount, $input->unit->factorToBase(), $this->scale());

        return new Quantity(
            amount: $amount,
            unit: $input->unit,
            measurementGroup: $input->unit->measurementGroup(),
            compatibilityKey: CompatibilityKey::forUnit($input->unit, $input->ingredientId),
            normalizedAmount: $this->canonical($normalizedAmount),
            ingredientId: $input->ingredientId,
        );
    }

    /** @param  numeric-string  $packageCount */
    private function normalizePackage(QuantityInput $input, string $packageCount): Quantity
    {
        if ($input->ingredientId === null || $input->ingredientPackageId === null) {
            throw new InvalidArgumentException('Package quantities require an ingredient and package definition.');
        }

        if (! $input->hasKnownPackageContents()) {
            return new Quantity(
                amount: $packageCount,
                unit: null,
                measurementGroup: MeasurementGroup::Package,
                compatibilityKey: CompatibilityKey::forUnknownPackage($input->ingredientPackageId),
                normalizedAmount: $this->canonical($packageCount),
                ingredientId: $input->ingredientId,
                ingredientPackageId: $input->ingredientPackageId,
            );
        }

        if (! $input->packageContentUnit?->isMetricContentUnit()) {
            throw new InvalidArgumentException('Known package contents must use a mass or volume unit.');
        }

        $contentAmount = $this->parser->parse((string) $input->packageContentAmount);
        $contentNormalized = bcmul($contentAmount, $input->packageContentUnit->factorToBase(), $this->scale());
        $normalizedAmount = bcmul($packageCount, $contentNormalized, $this->scale());

        return new Quantity(
            amount: $packageCount,
            unit: null,
            measurementGroup: $input->packageContentUnit->measurementGroup(),
            compatibilityKey: CompatibilityKey::forUnit($input->packageContentUnit, $input->ingredientId),
            normalizedAmount: $this->canonical($normalizedAmount),
            ingredientId: $input->ingredientId,
            ingredientPackageId: $input->ingredientPackageId,
        );
    }

    private function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
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
            throw new InvalidArgumentException('The normalized quantity is not numeric.');
        }

        return $value;
    }
}
