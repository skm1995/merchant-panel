<?php

declare(strict_types=1);

namespace App\Shared\Http\Enum;

enum ResponseStatusEnum: int
{
    case OK = 200;
    case CREATED = 201;
    case ACCEPTED = 202;
    case NO_CONTENT = 204;
    case MOVED_PERMANENTLY = 301;
    case SEE_OTHER = 303;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case CONFLICT = 409;
    case PRECONDITION_FAILED = 412;
    case UNPROCESSABLE_CONTENT = 422;
    case PRECONDITION_REQUIRED = 428;
    case TOO_MANY_REQUESTS = 429;

    public function message(): string
    {
        return match ($this) {
            self::OK => 'OK',
            self::CREATED => 'Created',
            self::ACCEPTED => 'Accepted',
            self::NO_CONTENT => 'No Content',
            self::MOVED_PERMANENTLY => 'Moved Permanently',
            self::SEE_OTHER => 'See Other',
            self::BAD_REQUEST => 'Bad Request',
            self::UNAUTHORIZED => 'Unauthorized',
            self::FORBIDDEN => 'Forbidden',
            self::NOT_FOUND => 'Not Found',
            self::CONFLICT => 'Conflict',
            self::PRECONDITION_FAILED => 'Precondition Failed',
            self::UNPROCESSABLE_CONTENT => 'Unprocessable Content',
            self::PRECONDITION_REQUIRED => 'Precondition Required',
            self::TOO_MANY_REQUESTS => 'Too Many Requests',
        };
    }
}
