<?php

declare(strict_types=1);

namespace App\Shared\Http\Enum;

enum HttpMethodEnum: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
}
