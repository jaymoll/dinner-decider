<?php

namespace Tests\Unit\Measurements;

use App\Services\Measurements\QuantityInputParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuantityInputParserTest extends TestCase
{
    #[DataProvider('validQuantities')]
    public function test_it_parses_supported_decimal_and_fraction_inputs(string $input, string $expected): void
    {
        $this->assertSame($expected, (new QuantityInputParser)->parse($input));
    }

    /** @return array<string, array{string, string}> */
    public static function validQuantities(): array
    {
        return [
            'decimal point' => ['0.5', '0.5'],
            'decimal comma' => ['0,5', '0.5'],
            'simple fraction' => ['1/2', '0.5'],
            'mixed fraction' => ['1 1/2', '1.5'],
            'unicode fraction' => ['¾', '0.75'],
            'unicode mixed fraction' => ['2½', '2.5'],
            'whole amount keeps trailing zeroes' => ['1000', '1000'],
        ];
    }

    #[DataProvider('invalidQuantities')]
    public function test_it_rejects_invalid_quantities(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QuantityInputParser)->parse($input);
    }

    /** @return array<string, array{string}> */
    public static function invalidQuantities(): array
    {
        return [
            'empty' => [''],
            'zero' => ['0'],
            'negative' => ['-1'],
            'division by zero' => ['1/0'],
            'ambiguous separators' => ['1,000.5'],
            'expression' => ['1+2'],
            'excess precision' => ['0.1234567'],
        ];
    }
}
