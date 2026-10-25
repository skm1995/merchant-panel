<?php

declare(strict_types=1);

namespace App\Shared\Http\Enum;

enum ResponseStatusEnum: int
{
    case OK = 200;
    case SEE_OTHER = 303;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;

    public function message(): string
    {
        return match ($this) {
            self::OK => 'OK',
            self::SEE_OTHER => 'See Other',
            self::FORBIDDEN => 'Forbidden',
            self::NOT_FOUND => 'Not Found',
        };
    }
}
