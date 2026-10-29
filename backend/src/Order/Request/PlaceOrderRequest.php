<?php

declare(strict_types=1);

namespace App\Order\Request;

use App\Cart\Entity\CartId;
use App\Order\Command\PlaceOrderCommand;
use App\Order\Enum\ReservedOrderKeyPrefixEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\IdempotencyKey;
use App\Shared\Http\Request;

final class PlaceOrderRequest
{
    private const string CART_ID_PARAMETER = 'id';
    private const string RESERVED_KEY_FORMAT = 'The Idempotency-Key must not start with "%s", which is reserved for the shop\'s own orders.';

    /**
     * Only the Black Friday simulator may use its reserved key prefix; the generated history
     * never goes through checkout.
     */
    public static function toCommand(Request $request, bool $fromSimulator): PlaceOrderCommand
    {
        $key = IdempotencyKey::parse($request);
        $reserved = ReservedOrderKeyPrefixEnum::of($key);

        if ($reserved !== null && !($fromSimulator && $reserved === ReservedOrderKeyPrefixEnum::SIMULATION)) {
            throw new InvalidRequestException(sprintf(self::RESERVED_KEY_FORMAT, $reserved->value));
        }

        return new PlaceOrderCommand(CartId::fromString($request->routeParameter(self::CART_ID_PARAMETER)), $key);
    }
}
