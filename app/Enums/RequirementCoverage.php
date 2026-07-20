<?php

namespace App\Enums;

enum RequirementCoverage: string
{
    case Full = 'full';
    case Partial = 'partial';
    case Missing = 'missing';
    case Incompatible = 'incompatible';
    case Staple = 'staple';
    case Unavailable = 'unavailable';
    case NonExact = 'non_exact';
}
