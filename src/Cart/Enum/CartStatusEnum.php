<?php

declare(strict_types=1);

namespace App\Cart\Enum;

enum CartStatusEnum: string
{
    case OPEN = 'open';
    case CHECKED_OUT = 'checked_out';
}
