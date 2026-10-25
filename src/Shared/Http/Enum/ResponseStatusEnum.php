<?php

declare(strict_types=1);

namespace App\Shared\Http\Enum;

enum ResponseStatusEnum: int
{
    case OK = 200;
    case CREATED = 201;
    case SEE_OTHER = 303;
    case BAD_REQUEST = 400;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case CONFLICT = 409;
    case UNPROCESSABLE_CONTENT = 422;

    public function message(): string
    {
        return match ($this) {
            self::OK => 'OK',
            self::CREATED => 'Created',
            self::SEE_OTHER => 'See Other',
            self::BAD_REQUEST => 'Bad Request',
            self::FORBIDDEN => 'Forbidden',
            self::NOT_FOUND => 'Not Found',
            self::CONFLICT => 'Conflict',
            self::UNPROCESSABLE_CONTENT => 'Unprocessable Content',
        };
    }
}
