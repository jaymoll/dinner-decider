<?php

namespace App\Enums;

enum MeasurementGroup: string
{
    case Mass = 'mass';
    case Volume = 'volume';
    case Count = 'count';
    case Package = 'package';
}
