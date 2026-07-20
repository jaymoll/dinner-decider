<?php

namespace App\ValueObjects;

use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use InvalidArgumentException;

final readonly class Quantity
{
    /** @var numeric-string */
    public string $amount;

    /** @var numeric-string */
    public string $normalizedAmount;

    public ?UnitCode $unit;

    public MeasurementGroup $measurementGroup;

    public CompatibilityKey $compatibilityKey;

    public ?int $ingredientId;

    public ?int $ingredientPackageId;

    public function __construct(
        string $amount,
        ?UnitCode $unit,
        MeasurementGroup $measurementGroup,
        CompatibilityKey $compatibilityKey,
        string $normalizedAmount,
        ?int $ingredientId = null,
        ?int $ingredientPackageId = null,
    ) {
        if (! is_numeric($amount) || ! is_numeric($normalizedAmount)) {
            throw new InvalidArgumentException('A quantity must use numeric decimal strings.');
        }

        if (bccomp($amount, '0', self::scale()) <= 0 || bccomp($normalizedAmount, '0', self::scale()) <= 0) {
            throw new InvalidArgumentException('A quantity must be greater than zero.');
        }

        if ($unit !== null && $unit->measurementGroup() !== $measurementGroup) {
            throw new InvalidArgumentException('The unit does not belong to the quantity measurement group.');
        }

        if ($measurementGroup === MeasurementGroup::Count && $unit === null) {
            throw new InvalidArgumentException('A semantic count quantity requires a unit.');
        }

        $this->amount = $amount;
        $this->unit = $unit;
        $this->measurementGroup = $measurementGroup;
        $this->compatibilityKey = $compatibilityKey;
        $this->normalizedAmount = $normalizedAmount;
        $this->ingredientId = $ingredientId;
        $this->ingredientPackageId = $ingredientPackageId;
    }

    public function isCompatibleWith(self $other): bool
    {
        return $this->ingredientId === $other->ingredientId
            && $this->compatibilityKey->equals($other->compatibilityKey);
    }

    public function compare(self $other): int
    {
        $this->assertCompatible($other);

        return bccomp($this->normalizedAmount, $other->normalizedAmount, self::scale());
    }

    public function add(self $other): self
    {
        $this->assertCompatible($other);

        if ($this->unit === null && $this->ingredientPackageId !== $other->ingredientPackageId) {
            return $this->inBaseUnit(bcadd($this->normalizedAmount, $other->normalizedAmount, self::scale()));
        }

        return $this->withAmounts(
            bcadd($this->amount, $this->amountInCurrentRepresentation($other), self::scale()),
            bcadd($this->normalizedAmount, $other->normalizedAmount, self::scale()),
        );
    }

    public function subtract(self $other): self
    {
        $this->assertCompatible($other);
        $normalizedAmount = bcsub($this->normalizedAmount, $other->normalizedAmount, self::scale());

        if (bccomp($normalizedAmount, '0', self::scale()) <= 0) {
            throw new InvalidArgumentException('Subtraction must leave a positive quantity.');
        }

        if ($this->unit === null && $this->ingredientPackageId !== $other->ingredientPackageId) {
            return $this->inBaseUnit($normalizedAmount);
        }

        return $this->withAmounts(
            bcsub($this->amount, $this->amountInCurrentRepresentation($other), self::scale()),
            $normalizedAmount,
        );
    }

    /** @param  numeric-string  $factor */
    public function scaleBy(string $factor): self
    {
        if (bccomp($factor, '0', self::scale()) <= 0) {
            throw new InvalidArgumentException('A scaling factor must be greater than zero.');
        }

        return $this->withAmounts(
            bcmul($this->amount, $factor, self::scale()),
            bcmul($this->normalizedAmount, $factor, self::scale()),
        );
    }

    /** @return numeric-string */
    private function amountInCurrentRepresentation(self $other): string
    {
        if ($this->unit !== null) {
            return bcdiv($other->normalizedAmount, $this->unit->factorToBase(), self::scale());
        }

        if ($this->ingredientPackageId === $other->ingredientPackageId) {
            return $other->amount;
        }

        return $other->normalizedAmount;
    }

    /**
     * @param  numeric-string  $amount
     * @param  numeric-string  $normalizedAmount
     */
    private function withAmounts(string $amount, string $normalizedAmount): self
    {
        return new self(
            amount: self::canonical($amount),
            unit: $this->unit,
            measurementGroup: $this->measurementGroup,
            compatibilityKey: $this->compatibilityKey,
            normalizedAmount: self::canonical($normalizedAmount),
            ingredientId: $this->ingredientId,
            ingredientPackageId: $this->ingredientPackageId,
        );
    }

    /** @param  numeric-string  $normalizedAmount */
    private function inBaseUnit(string $normalizedAmount): self
    {
        $baseUnit = match ($this->measurementGroup) {
            MeasurementGroup::Mass => UnitCode::Gram,
            MeasurementGroup::Volume => UnitCode::Millilitre,
            MeasurementGroup::Count => $this->unit ?? throw new InvalidArgumentException('A count quantity requires a semantic unit.'),
            MeasurementGroup::Package => throw new InvalidArgumentException('Unknown packages only combine within the same package definition.'),
        };

        return new self(
            amount: self::canonical($normalizedAmount),
            unit: $baseUnit,
            measurementGroup: $this->measurementGroup,
            compatibilityKey: $this->compatibilityKey,
            normalizedAmount: self::canonical($normalizedAmount),
            ingredientId: $this->ingredientId,
        );
    }

    private function assertCompatible(self $other): void
    {
        if (! $this->isCompatibleWith($other)) {
            throw new InvalidArgumentException('Incompatible quantities cannot be combined.');
        }
    }

    /**
     * @param  numeric-string  $value
     * @return numeric-string
     */
    private static function canonical(string $value): string
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

    private static function scale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }
}
