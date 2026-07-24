<?php

namespace App\ValueObjects;

use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use InvalidArgumentException;

/**
 * Identifies quantities that may safely share stock without implicit cross-ingredient conversion.
 */
final readonly class CompatibilityKey
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('A compatibility key cannot be empty.');
        }
    }

    public static function forUnit(UnitCode $unit, ?int $ingredientId = null): self
    {
        return match ($unit->measurementGroup()) {
            MeasurementGroup::Mass => new self('mass'),
            MeasurementGroup::Volume => new self('volume'),
            // Counts such as cloves and slices are semantic, so both ingredient and unit matter.
            MeasurementGroup::Count => $ingredientId !== null
                ? new self("count:{$ingredientId}:{$unit->value}")
                : throw new InvalidArgumentException('Semantic count quantities require an ingredient.'),
            MeasurementGroup::Package => throw new InvalidArgumentException('Package compatibility requires a package definition.'),
        };
    }

    public static function forUnknownPackage(int $ingredientPackageId): self
    {
        return new self("package:{$ingredientPackageId}");
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
