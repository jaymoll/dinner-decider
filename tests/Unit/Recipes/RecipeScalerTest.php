<?php

namespace Tests\Unit\Recipes;

use App\Data\Measurements\QuantityInput;
use App\Enums\UnitCode;
use App\Services\Measurements\QuantityInputParser;
use App\Services\Measurements\UnitConverter;
use App\Services\Recipes\RecipeScaler;
use InvalidArgumentException;
use Tests\TestCase;

class RecipeScalerTest extends TestCase
{
    public function test_it_scales_from_the_original_quantity(): void
    {
        $source = $this->converter()->normalize(new QuantityInput('400', UnitCode::Gram, 1));
        $scaler = new RecipeScaler;

        $this->assertSame('200', $scaler->scaleQuantity($source, '2', '4')->normalizedAmount);
        $this->assertSame('600', $scaler->scaleQuantity($source, '6', '4')->normalizedAmount);
        $this->assertSame('400', $source->normalizedAmount);
    }

    public function test_it_scales_known_package_contents_without_rounding_to_packages(): void
    {
        $source = $this->converter()->normalize(new QuantityInput('1', ingredientId: 1, ingredientPackageId: 2, packageContentAmount: '400', packageContentUnit: UnitCode::Gram));

        $scaled = new RecipeScaler()->scaleQuantity($source, '3', '4');

        $this->assertSame('0.75', $scaled->amount);
        $this->assertSame('300', $scaled->normalizedAmount);
    }

    public function test_non_exact_requirements_remain_unchanged(): void
    {
        $scaled = new RecipeScaler()->scale([
            ['quantity' => null, 'description' => 'Salt to taste'],
        ], '2', '4');

        $this->assertNull($scaled[0]['quantity']);
        $this->assertSame('Salt to taste', $scaled[0]['description']);
    }

    public function test_it_rejects_non_positive_servings(): void
    {
        $source = $this->converter()->normalize(new QuantityInput('400', UnitCode::Gram, 1));

        $this->expectException(InvalidArgumentException::class);

        new RecipeScaler()->scaleQuantity($source, '0', '4');
    }

    private function converter(): UnitConverter
    {
        return new UnitConverter(new QuantityInputParser);
    }
}
