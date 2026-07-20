<?php

namespace Tests\Unit\Measurements;

use App\Data\Measurements\QuantityInput;
use App\Enums\MeasurementGroup;
use App\Enums\UnitCode;
use App\Services\Measurements\QuantityInputParser;
use App\Services\Measurements\UnitConverter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnitConverterTest extends TestCase
{
    #[DataProvider('metricConversions')]
    public function test_it_normalizes_metric_units(UnitCode $unit, string $amount, string $expected): void
    {
        $quantity = $this->converter()->normalize(new QuantityInput($amount, $unit, 1));

        $this->assertSame($expected, $quantity->normalizedAmount);
    }

    /** @return array<string, array{UnitCode, string, string}> */
    public static function metricConversions(): array
    {
        return [
            'milligrams' => [UnitCode::Milligram, '500', '0.5'],
            'kilograms' => [UnitCode::Kilogram, '1.5', '1500'],
            'litres' => [UnitCode::Litre, '1.25', '1250'],
            'teaspoons' => [UnitCode::Teaspoon, '2', '10'],
            'tablespoons' => [UnitCode::Tablespoon, '1', '15'],
        ];
    }

    public function test_semantic_counts_are_ingredient_and_unit_specific(): void
    {
        $pieces = $this->converter()->normalize(new QuantityInput('2', UnitCode::Piece, 10));
        $slices = $this->converter()->normalize(new QuantityInput('2', UnitCode::Slice, 10));
        $otherPieces = $this->converter()->normalize(new QuantityInput('2', UnitCode::Piece, 11));

        $this->assertFalse($pieces->isCompatibleWith($slices));
        $this->assertFalse($pieces->isCompatibleWith($otherPieces));
    }

    public function test_known_packages_normalize_to_metric_contents(): void
    {
        $quantity = $this->converter()->normalize(new QuantityInput(
            amount: '2',
            ingredientId: 4,
            ingredientPackageId: 7,
            packageContentAmount: '400',
            packageContentUnit: UnitCode::Gram,
        ));

        $this->assertSame(MeasurementGroup::Mass, $quantity->measurementGroup);
        $this->assertSame('800', $quantity->normalizedAmount);
        $this->assertSame('mass', (string) $quantity->compatibilityKey);
    }

    public function test_unknown_packages_only_match_the_same_package_definition(): void
    {
        $first = $this->converter()->normalize(new QuantityInput('2', ingredientId: 4, ingredientPackageId: 7));
        $same = $this->converter()->normalize(new QuantityInput('1', ingredientId: 4, ingredientPackageId: 7));
        $other = $this->converter()->normalize(new QuantityInput('1', ingredientId: 4, ingredientPackageId: 8));

        $this->assertTrue($first->isCompatibleWith($same));
        $this->assertFalse($first->isCompatibleWith($other));
    }

    public function test_packages_reject_count_content_units(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->converter()->normalize(new QuantityInput('1', ingredientId: 4, ingredientPackageId: 7, packageContentAmount: '2', packageContentUnit: UnitCode::Piece));
    }

    private function converter(): UnitConverter
    {
        return new UnitConverter(new QuantityInputParser);
    }
}
