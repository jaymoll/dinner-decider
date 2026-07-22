<?php

namespace App\Enums;

enum GroceryItemSource: string
{
    case Generated = 'generated';
    case Manual = 'manual';
}
