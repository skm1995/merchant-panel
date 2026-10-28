<?php

declare(strict_types=1);

namespace App\Order\Message;

use App\Order\Entity\OrderId;
use App\Shared\Messaging\Message;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;

/**
 * Recorded through the outbox in the transaction that marks an order paid, so features that
 * follow sales, such as the daily sales summary, learn about it only after the commit.
 */
#[Message(transport: 'orders')]
final readonly class OrderPaidMessage implements OutboxMessageInterface
{
    private const string TYPE = 'order.paid';
    private const string ORDER_ID_KEY = 'order_id';

    public function __construct(public OrderId $orderId)
    {
    }

    public static function messageType(): string
    {
        return self::TYPE;
    }

    public static function fromArray(array $data): static
    {
        return new self(OrderId::fromString((string) $data[self::ORDER_ID_KEY]));
    }

    public function toArray(): array
    {
        return [self::ORDER_ID_KEY => $this->orderId->value];
    }
}
