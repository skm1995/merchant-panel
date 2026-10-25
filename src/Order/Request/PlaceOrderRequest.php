<?php

declare(strict_types=1);

namespace App\Order\Request;

use App\Cart\Entity\CartId;
use App\Order\Command\PlaceOrderCommand;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

final readonly class PlaceOrderRequest
{
    public const int MAXIMUM_KEY_LENGTH = 255;
    private const string CART_ID_PARAMETER = 'id';
    private const string IDEMPOTENCY_KEY_HEADER = 'Idempotency-Key';
    private const string PRINTABLE_KEY_PATTERN = '/^[\x21-\x7e]{1,255}$/';
    private const string INVALID_KEY_MESSAGE = 'The Idempotency-Key header is required and must contain 1 to 255 printable ASCII characters.';

    public function __construct(public CartId $cartId, public string $idempotencyKey)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $key = $request->header(self::IDEMPOTENCY_KEY_HEADER);

        if ($key === null || preg_match(self::PRINTABLE_KEY_PATTERN, $key) !== 1) {
            throw new InvalidRequestException(self::INVALID_KEY_MESSAGE);
        }

        return new self(CartId::fromString((string) $request->routeParameter(self::CART_ID_PARAMETER)), $key);
    }

    public function toCommand(): PlaceOrderCommand
    {
        return new PlaceOrderCommand($this->cartId, $this->idempotencyKey);
    }
}
