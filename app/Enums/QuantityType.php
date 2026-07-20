<?php

namespace App\Enums;

enum QuantityType: string
{
    case Exact = 'exact';
    case NonExact = 'non_exact';
}
