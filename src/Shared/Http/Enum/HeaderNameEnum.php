<?php

declare(strict_types=1);

namespace App\Shared\Http\Enum;

enum HeaderNameEnum: string
{
    case CONTENT_TYPE = 'Content-Type';
    case LOCATION = 'Location';
}
