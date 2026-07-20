<?php

namespace Tests\Unit\Measurements;

use App\Data\Measurements\QuantityInput;
use App\Enums\UnitCode;
use App\Services\Measurements\QuantityFormatter;
use App\Services\Measurements\QuantityInputParser;
use App\Services\Measurements\UnitConverter;
use Tests\TestCase;

class QuantityFormatterTest extends TestCase
{
    public function test_it_formats_common_count_fractions(): void
    {
        $quantity = $this->converter()->normalize(new QuantityInput('1.5', UnitCode::Piece, 1));

        $this->assertSame('1½ piece', (new QuantityFormatter)->format($quantity));
    }

    public function test_it_limits_display_precision_without_changing_calculation_precision(): void
    {
        $quantity = $this->converter()->normalize(new QuantityInput('1.234567', UnitCode::Gram, 1));

        $this->assertSame('1.23 g', (new QuantityFormatter)->format($quantity));
        $this->assertSame('1.234567', $quantity->normalizedAmount);
    }

    private function converter(): UnitConverter
    {
        return new UnitConverter(new QuantityInputParser);
    }
}
