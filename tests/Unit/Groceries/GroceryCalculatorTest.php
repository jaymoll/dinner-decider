<?php

namespace Tests\Unit\Groceries;

use App\Data\Groceries\GroceryRequirementData;
use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\RequirementCoverage;
use App\Enums\UnitCode;
use App\Services\Groceries\GroceryCalculator;
use PHPUnit\Framework\TestCase;

class GroceryCalculatorTest extends TestCase
{
    public function test_it_aggregates_compatible_shortfalls_and_keeps_semantic_counts_isolated(): void
    {
        $items = (new GroceryCalculator)->calculate([
            $this->requirement(1, 'mass', '500'), $this->requirement(2, 'mass', '250'),
            $this->requirement(3, 'count:10:piece', '2'), $this->requirement(4, 'count:10:slice', '3'),
        ]);

        $this->assertCount(3, $items);
        $mass = collect($items)->firstWhere('calculatedAmount', '750.000000');
        $this->assertNotNull($mass);
        $this->assertSame('g', $mass->calculatedUnit?->value);
        $this->assertCount(2, $mass->contributions);
    }

    public function test_required_non_exact_is_generated_but_optional_and_covered_rows_are_excluded(): void
    {
        $items = (new GroceryCalculator)->calculate([
            $this->requirement(1, null, null, QuantityType::NonExact, NonExactStatus::Required, RequirementCoverage::Unavailable, 'To taste'),
            $this->requirement(2, null, null, QuantityType::NonExact, NonExactStatus::Optional, RequirementCoverage::Unavailable, 'For serving'),
            $this->requirement(3, null, null, QuantityType::NonExact, NonExactStatus::Required, RequirementCoverage::NonExact, 'To taste'),
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('To taste', $items[0]->quantityDescription);
        $this->assertNull($items[0]->calculatedAmount);
    }

    public function test_keys_and_totals_are_deterministic(): void
    {
        $calculator = new GroceryCalculator;
        $first = $calculator->calculate([$this->requirement(1, 'volume', '15'), $this->requirement(2, 'volume', '5')]);
        $second = $calculator->calculate([$this->requirement(2, 'volume', '5'), $this->requirement(1, 'volume', '15')]);

        $this->assertSame($first[0]->generationKey, $second[0]->generationKey);
        $this->assertSame($first[0]->calculatedAmount, $second[0]->calculatedAmount);
    }

    public function test_known_packages_combine_as_metric_and_unknown_packages_stay_isolated(): void
    {
        $known = new GroceryRequirementData(1, 10, 'Tomatoes', 'Canned goods', QuantityType::Exact, null, RequirementCoverage::Missing, '800', 'mass', null, 5, '400 g can', UnitCode::Gram);
        $unknown = new GroceryRequirementData(2, 10, 'Tomatoes', 'Canned goods', QuantityType::Exact, null, RequirementCoverage::Missing, '2', 'package:6', null, 6, 'small tin', null);

        $items = (new GroceryCalculator)->calculate([$known, $unknown]);

        $this->assertCount(2, $items);
        $metric = collect($items)->firstWhere('calculatedUnit', UnitCode::Gram);
        $this->assertSame('800.000000', $metric?->calculatedAmount);
        $this->assertSame(5, $metric?->ingredientPackageId);
        $package = collect($items)->firstWhere('calculatedUnit', null);
        $this->assertSame(6, $package?->ingredientPackageId);
        $this->assertSame('2.000000', $package?->calculatedAmount);
    }

    private function requirement(int $id, ?string $key, ?string $missing, QuantityType $type = QuantityType::Exact, ?NonExactStatus $status = null, RequirementCoverage $coverage = RequirementCoverage::Missing, ?string $description = null): GroceryRequirementData
    {
        return new GroceryRequirementData($id, 10, 'Ingredient', 'Dry goods', $type, $status, $coverage, $missing, $key, $description, null, null, null);
    }
}
