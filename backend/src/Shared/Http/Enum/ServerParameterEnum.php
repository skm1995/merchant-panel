<?php

declare(strict_types=1);

namespace App\Shared\Http\Enum;

enum ServerParameterEnum: string
{
    case REQUEST_METHOD = 'REQUEST_METHOD';
    case REQUEST_URI = 'REQUEST_URI';
    case HTTPS = 'HTTPS';
}
