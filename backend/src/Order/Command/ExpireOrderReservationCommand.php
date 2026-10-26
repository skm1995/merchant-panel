<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Order\Entity\OrderId;
use App\Shared\Messaging\Message;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;

/**
 * Scheduled through the outbox at checkout and delivered when the reservation ends.
 */
#[Message(transport: 'orders')]
final readonly class ExpireOrderReservationCommand implements OutboxMessageInterface
{
    private const string TYPE = 'order.expire_reservation';
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
