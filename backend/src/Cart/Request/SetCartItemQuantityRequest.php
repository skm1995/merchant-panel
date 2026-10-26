<?php

declare(strict_types=1);

namespace App\Cart\Request;

use App\Cart\Entity\CartId;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

final readonly class SetCartItemQuantityRequest
{
    private const string CART_ID_PARAMETER = 'id';
    private const string PRODUCT_ID_PARAMETER = 'productId';
    private const string QUANTITY_FIELD = 'quantity';
    private const string INVALID_QUANTITY_MESSAGE = 'The "quantity" field must be an integer.';

    public function __construct(public CartId $cartId, public int $productId, public int $quantity)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $quantity = $request->json()[self::QUANTITY_FIELD] ?? null;

        if (!is_int($quantity)) {
            throw new InvalidRequestException(self::INVALID_QUANTITY_MESSAGE);
        }

        return new self(
            CartId::fromString($request->routeParameter(self::CART_ID_PARAMETER)),
            (int) $request->routeParameter(self::PRODUCT_ID_PARAMETER),
            $quantity,
        );
    }
}
