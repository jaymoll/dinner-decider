<?php

namespace App\Enums;

enum PackageType: string
{
    case Can = 'can';
    case Jar = 'jar';
    case Pack = 'pack';
    case Bag = 'bag';
    case Bottle = 'bottle';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
