<?php

namespace App\Enums;

enum NonExactStatus: string
{
    case Required = 'required';
    case Optional = 'optional';
}
