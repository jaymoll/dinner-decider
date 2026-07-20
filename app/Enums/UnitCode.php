<?php

namespace App\Enums;

enum UnitCode: string
{
    case Milligram = 'mg';
    case Gram = 'g';
    case Kilogram = 'kg';
    case Millilitre = 'ml';
    case Litre = 'l';
    case Teaspoon = 'tsp';
    case Tablespoon = 'tbsp';
    case Piece = 'piece';
    case Clove = 'clove';
    case Bulb = 'bulb';
    case Slice = 'slice';
    case Leaf = 'leaf';
    case Stalk = 'stalk';
    case Sprig = 'sprig';

    public function measurementGroup(): MeasurementGroup
    {
        return match ($this) {
            self::Milligram, self::Gram, self::Kilogram => MeasurementGroup::Mass,
            self::Millilitre, self::Litre, self::Teaspoon, self::Tablespoon => MeasurementGroup::Volume,
            self::Piece, self::Clove, self::Bulb, self::Slice, self::Leaf, self::Stalk, self::Sprig => MeasurementGroup::Count,
        };
    }

    /** @return numeric-string */
    public function factorToBase(): string
    {
        return match ($this) {
            self::Milligram => '0.001',
            self::Gram, self::Millilitre,
            self::Piece, self::Clove, self::Bulb, self::Slice,
            self::Leaf, self::Stalk, self::Sprig => '1',
            self::Kilogram, self::Litre => '1000',
            self::Teaspoon => '5',
            self::Tablespoon => '15',
        };
    }

    public function baseUnit(): self
    {
        return match ($this->measurementGroup()) {
            MeasurementGroup::Mass => self::Gram,
            MeasurementGroup::Volume => self::Millilitre,
            MeasurementGroup::Count => $this,
            MeasurementGroup::Package => throw new \LogicException('Package quantities do not have a UnitCode base unit.'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Milligram => 'Milligram',
            self::Gram => 'Gram',
            self::Kilogram => 'Kilogram',
            self::Millilitre => 'Millilitre',
            self::Litre => 'Litre',
            self::Teaspoon => 'Teaspoon',
            self::Tablespoon => 'Tablespoon',
            self::Piece => 'Piece',
            self::Clove => 'Clove',
            self::Bulb => 'Bulb',
            self::Slice => 'Slice',
            self::Leaf => 'Leaf',
            self::Stalk => 'Stalk',
            self::Sprig => 'Sprig',
        };
    }

    public function isMetricContentUnit(): bool
    {
        return in_array($this->measurementGroup(), [MeasurementGroup::Mass, MeasurementGroup::Volume], true);
    }
}
