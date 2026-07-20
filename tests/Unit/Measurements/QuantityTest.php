<?php

namespace Tests\Unit\Measurements;

use App\Data\Measurements\QuantityInput;
use App\Enums\UnitCode;
use App\Services\Measurements\QuantityInputParser;
use App\Services\Measurements\UnitConverter;
use InvalidArgumentException;
use Tests\TestCase;

class QuantityTest extends TestCase
{
    public function test_compatible_quantities_add_without_floating_point_drift(): void
    {
        $converter = $this->converter();
        $kilograms = $converter->normalize(new QuantityInput('1', UnitCode::Kilogram, 1));
        $grams = $converter->normalize(new QuantityInput('500', UnitCode::Gram, 1));

        $combined = $kilograms->add($grams);

        $this->assertSame('1.5', $combined->amount);
        $this->assertSame('1500', $combined->normalizedAmount);
    }

    public function test_incompatible_measurements_cannot_be_combined(): void
    {
        $converter = $this->converter();

        $this->expectException(InvalidArgumentException::class);

        $converter->normalize(new QuantityInput('100', UnitCode::Gram, 1))
            ->add($converter->normalize(new QuantityInput('100', UnitCode::Millilitre, 1)));
    }

    public function test_repeated_scaling_always_uses_the_source_quantity(): void
    {
        $source = $this->converter()->normalize(new QuantityInput('400', UnitCode::Gram, 1));

        $this->assertSame('300', $source->scaleBy('0.75')->normalizedAmount);
        $this->assertSame('600', $source->scaleBy('1.5')->normalizedAmount);
        $this->assertSame('400', $source->normalizedAmount);
    }

    private function converter(): UnitConverter
    {
        return new UnitConverter(new QuantityInputParser);
    }
}
