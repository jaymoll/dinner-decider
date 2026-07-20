<?php

namespace App\Data\Measurements;

use App\Enums\UnitCode;

final readonly class QuantityInput
{
    public function __construct(
        public string $amount,
        public ?UnitCode $unit = null,
        public ?int $ingredientId = null,
        public ?int $ingredientPackageId = null,
        public ?string $packageContentAmount = null,
        public ?UnitCode $packageContentUnit = null,
    ) {}

    public function isPackage(): bool
    {
        return $this->ingredientPackageId !== null;
    }

    public function hasKnownPackageContents(): bool
    {
        return $this->isPackage()
            && $this->packageContentAmount !== null
            && $this->packageContentUnit !== null;
    }
}
