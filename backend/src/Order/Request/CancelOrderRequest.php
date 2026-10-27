<?php

declare(strict_types=1);

namespace App\Order\Request;

use App\Order\Command\CancelOrderCommand;
use App\Order\Entity\OrderId;
use App\Shared\Http\EntityTag;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Exception\PreconditionRequiredException;
use App\Shared\Http\Request;

final class CancelOrderRequest
{
    private const string ORDER_ID_PARAMETER = 'id';
    private const string MISSING_PRECONDITION_MESSAGE = 'Send the order ETag in the If-Match header to cancel it.';

    public static function toCommand(Request $request): CancelOrderCommand
    {
        $ifMatch = $request->header(HeaderNameEnum::IF_MATCH->value)
            ?? throw new PreconditionRequiredException(self::MISSING_PRECONDITION_MESSAGE);

        return new CancelOrderCommand(
            OrderId::fromString($request->routeParameter(self::ORDER_ID_PARAMETER)),
            OrderAccessTokenHeader::read($request),
            EntityTag::fromHeader($ifMatch)->version,
        );
    }
}
