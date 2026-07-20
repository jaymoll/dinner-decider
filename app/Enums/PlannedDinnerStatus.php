<?php

namespace App\Enums;

enum PlannedDinnerStatus: string
{
    case Planned = 'planned';
    case Cooked = 'cooked';
    case Cancelled = 'cancelled';
}
