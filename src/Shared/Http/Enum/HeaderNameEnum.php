<?php

declare(strict_types=1);

namespace App\Shared\Http\Enum;

enum HeaderNameEnum: string
{
    case CONTENT_TYPE = 'Content-Type';
    case LOCATION = 'Location';
    case ETAG = 'ETag';
    case IF_MATCH = 'If-Match';
    case API_KEY = 'X-Api-Key';
    case IDEMPOTENCY_KEY = 'Idempotency-Key';
}
