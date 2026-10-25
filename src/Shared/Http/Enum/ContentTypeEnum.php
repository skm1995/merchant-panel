<?php

declare(strict_types=1);

namespace App\Shared\Http\Enum;

enum ContentTypeEnum: string
{
    case HTML = 'text/html; charset=UTF-8';
    case PLAIN = 'text/plain; charset=utf-8';
}
