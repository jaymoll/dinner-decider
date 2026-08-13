<?php

namespace App\Enums;

enum PlannedDinnerStatusEventType: string
{
    case Planned = 'planned';
    case Cancelled = 'cancelled';
    case Restored = 'restored';
    case Cooked = 'cooked';
}
