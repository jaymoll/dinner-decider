<?php

namespace Tests\Unit\DinnerPlans;

use App\Services\DinnerPlans\PantryAllocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PantryAllocatorTest extends TestCase
{
    public function test_it_allocates_across_multiple_entries_and_returns_a_partial_total(): void
    {
        $allocations = (new PantryAllocator)->allocate('100', [
            ['id' => 2, 'available_amount' => '30', 'native' => false],
            ['id' => 1, 'available_amount' => '40', 'native' => true],
        ]);

        $this->assertSame([1, 2], array_column($allocations, 'pantryEntryId'));
        $this->assertSame(['40', '30'], array_column($allocations, 'normalizedAmount'));
    }

    #[DataProvider('deterministicOrders')]
    public function test_native_representation_then_id_define_deterministic_order(array $entries, array $expected): void
    {
        $allocations = (new PantryAllocator)->allocate('3', $entries);

        $this->assertSame($expected, array_column($allocations, 'pantryEntryId'));
    }

    public static function deterministicOrders(): array
    {
        return [
            'native before convertible' => [[
                ['id' => 1, 'available_amount' => '2', 'native' => false],
                ['id' => 9, 'available_amount' => '2', 'native' => true],
            ], [9, 1]],
            'id breaks equal representation ties' => [[
                ['id' => 8, 'available_amount' => '2', 'native' => false],
                ['id' => 3, 'available_amount' => '2', 'native' => false],
            ], [3, 8]],
        ];
    }
}
