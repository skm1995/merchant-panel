<?php

declare(strict_types=1);

namespace App\Order\Request;

use App\Cart\Entity\CartId;
use App\Order\Command\PlaceOrderCommand;
use App\Shared\Http\IdempotencyKey;
use App\Shared\Http\Request;

final class PlaceOrderRequest
{
    private const string CART_ID_PARAMETER = 'id';

    public static function toCommand(Request $request): PlaceOrderCommand
    {
        return new PlaceOrderCommand(
            CartId::fromString($request->routeParameter(self::CART_ID_PARAMETER)),
            IdempotencyKey::parse($request),
        );
    }
}
