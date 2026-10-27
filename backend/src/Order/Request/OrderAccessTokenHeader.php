<?php

declare(strict_types=1);

namespace App\Order\Request;

use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

/**
 * Reads the X-Order-Token header that guests send to see, cancel, or pay for their order.
 */
final class OrderAccessTokenHeader
{
    private const string MISSING_TOKEN_MESSAGE = 'Send the order access token from checkout in the X-Order-Token header.';

    public static function read(Request $request): string
    {
        return $request->header(HeaderNameEnum::ORDER_TOKEN->value)
            ?? throw new InvalidRequestException(self::MISSING_TOKEN_MESSAGE);
    }
}
