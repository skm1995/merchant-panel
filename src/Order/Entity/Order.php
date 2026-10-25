<?php

declare(strict_types=1);

namespace App\Order\Entity;

use App\Cart\Entity\CartId;
use App\Order\Enum\OrderStatusEnum;
use DateTimeImmutable;
use InvalidArgumentException;
use Money\Money;

/**
 * Write model of an order at placement time.
 */
final readonly class Order
{
    private const string EMPTY_ORDER_MESSAGE = 'An order needs at least one line.';

    /** @param non-empty-list<OrderLine> $lines */
    private function __construct(
        public OrderId $id,
        public CartId $cartId,
        public string $idempotencyKey,
        public OrderStatusEnum $status,
        public array $lines,
        public DateTimeImmutable $reservationExpiresAt,
    ) {
    }

    /** @param list<OrderLine> $lines */
    public static function place(
        OrderId $id,
        CartId $cartId,
        string $idempotencyKey,
        array $lines,
        DateTimeImmutable $reservationExpiresAt,
    ): self {
        if ($lines === []) {
            throw new InvalidArgumentException(self::EMPTY_ORDER_MESSAGE);
        }

        return new self($id, $cartId, $idempotencyKey, OrderStatusEnum::PENDING_PAYMENT, $lines, $reservationExpiresAt);
    }

    public function total(): Money
    {
        return Money::sum(...array_map(static fn (OrderLine $line): Money => $line->lineTotal(), $this->lines));
    }
}
