<?php

declare(strict_types=1);

namespace App\Order\Request;

use App\Cart\Entity\CartId;
use App\Order\Command\PlaceOrderCommand;
use App\Shared\Http\IdempotencyKey;
use App\Shared\Http\Request;

final readonly class PlaceOrderRequest
{
    private const string CART_ID_PARAMETER = 'id';

    public function __construct(public CartId $cartId, public string $idempotencyKey)
    {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            CartId::fromString((string) $request->routeParameter(self::CART_ID_PARAMETER)),
            IdempotencyKey::fromRequest($request),
        );
    }

    public function toCommand(): PlaceOrderCommand
    {
        return new PlaceOrderCommand($this->cartId, $this->idempotencyKey);
    }
}
