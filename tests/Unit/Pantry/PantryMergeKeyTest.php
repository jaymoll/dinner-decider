<?php

namespace Tests\Unit\Pantry;

use App\Data\Measurements\QuantityInput;
use App\Enums\UnitCode;
use App\Models\PantryEntry;
use App\Services\Measurements\QuantityInputParser;
use App\Services\Measurements\UnitConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PantryMergeKeyTest extends TestCase
{
    #[DataProvider('compatibleDirectQuantities')]
    public function test_compatible_direct_quantities_share_a_merge_key(UnitCode $first, UnitCode $second): void
    {
        $converter = new UnitConverter(new QuantityInputParser);

        $this->assertSame(
            PantryEntry::mergeKeyFor($converter->normalize(new QuantityInput('1', $first, 10))),
            PantryEntry::mergeKeyFor($converter->normalize(new QuantityInput('1', $second, 10))),
        );
    }

    /** @return iterable<string, array{UnitCode, UnitCode}> */
    public static function compatibleDirectQuantities(): iterable
    {
        yield 'kilograms and grams' => [UnitCode::Kilogram, UnitCode::Gram];
        yield 'litres and millilitres' => [UnitCode::Litre, UnitCode::Millilitre];
    }

    public function test_semantic_count_units_and_package_definitions_stay_separate(): void
    {
        $converter = new UnitConverter(new QuantityInputParser);
        $cloves = $converter->normalize(new QuantityInput('1', UnitCode::Clove, 10));
        $bulbs = $converter->normalize(new QuantityInput('1', UnitCode::Bulb, 10));
        $knownPackage = $converter->normalize(new QuantityInput('1', ingredientId: 10, ingredientPackageId: 20, packageContentAmount: '400', packageContentUnit: UnitCode::Gram));
        $otherPackage = $converter->normalize(new QuantityInput('1', ingredientId: 10, ingredientPackageId: 21, packageContentAmount: '800', packageContentUnit: UnitCode::Gram));

        $this->assertNotSame(PantryEntry::mergeKeyFor($cloves), PantryEntry::mergeKeyFor($bulbs));
        $this->assertNotSame(PantryEntry::mergeKeyFor($knownPackage), PantryEntry::mergeKeyFor($otherPackage));
        $this->assertSame('mass', (string) $knownPackage->compatibilityKey);
        $this->assertSame('package:20', PantryEntry::mergeKeyFor($knownPackage));
    }

    public function test_cloves_bulbs_slices_and_pieces_are_pairwise_incompatible(): void
    {
        $converter = new UnitConverter(new QuantityInputParser);
        $keys = collect([UnitCode::Clove, UnitCode::Bulb, UnitCode::Slice, UnitCode::Piece])
            ->map(fn (UnitCode $unit): string => (string) $converter->normalize(new QuantityInput('1', $unit, 10))->compatibilityKey);

        $this->assertCount(4, $keys->unique());
    }
}
