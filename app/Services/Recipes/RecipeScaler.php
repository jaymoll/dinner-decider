<?php

namespace App\Services\Recipes;

use App\ValueObjects\Quantity;
use InvalidArgumentException;

final class RecipeScaler
{
    /**
     * @param  numeric-string  $selectedServings
     * @param  numeric-string  $defaultServings
     */
    public function scaleQuantity(Quantity $quantity, string $selectedServings, string $defaultServings): Quantity
    {
        $this->assertPositiveServings($selectedServings);
        $this->assertPositiveServings($defaultServings);

        $factor = bcdiv($selectedServings, $defaultServings, $this->calculationScale());

        return $quantity->scaleBy($factor);
    }

    /**
     * @param  list<array{quantity: Quantity|null, description: string|null}>  $requirements
     * @param  numeric-string  $selectedServings
     * @param  numeric-string  $defaultServings
     * @return list<array{quantity: Quantity|null, description: string|null}>
     */
    public function scale(array $requirements, string $selectedServings, string $defaultServings): array
    {
        return array_map(fn (array $requirement): array => [
            'quantity' => $requirement['quantity']?->scaleBy(
                $this->factor($selectedServings, $defaultServings),
            ),
            'description' => $requirement['description'],
        ], $requirements);
    }

    /**
     * @param  numeric-string  $selectedServings
     * @param  numeric-string  $defaultServings
     * @return numeric-string
     */
    private function factor(string $selectedServings, string $defaultServings): string
    {
        $this->assertPositiveServings($selectedServings);
        $this->assertPositiveServings($defaultServings);

        return bcdiv($selectedServings, $defaultServings, $this->calculationScale());
    }

    /** @param  numeric-string  $servings */
    private function assertPositiveServings(string $servings): void
    {
        if (! preg_match('/^\d+(?:\.\d+)?$/', $servings) || bccomp($servings, '0', $this->calculationScale()) <= 0) {
            throw new InvalidArgumentException('Servings must be greater than zero.');
        }
    }

    private function calculationScale(): int
    {
        return (int) config('measurements.calculation_scale', 6);
    }
}
