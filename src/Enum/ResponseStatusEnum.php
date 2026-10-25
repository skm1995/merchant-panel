<?php

declare(strict_types=1);

namespace App\Enum;

enum ResponseStatusEnum: int
{
    case OK = 200;
    case SEE_OTHER = 303;
    case NOT_FOUND = 404;

    public function message(): string
    {
        return match ($this) {
            self::OK => 'OK',
            self::SEE_OTHER => 'See Other',
            self::NOT_FOUND => 'Not Found',
        };
    }
}
