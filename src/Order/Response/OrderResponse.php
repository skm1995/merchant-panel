<?php

declare(strict_types=1);

namespace App\Order\Response;

use App\Order\ReadModel\OrderView;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;
use DateTimeInterface;

final readonly class OrderResponse implements Arrayable
{
    private const string ID_KEY = 'id';
    private const string STATUS_KEY = 'status';
    private const string ITEMS_KEY = 'items';
    private const string TOTAL_KEY = 'total';
    private const string RESERVATION_EXPIRES_AT_KEY = 'reservation_expires_at';
    private const string CREATED_AT_KEY = 'created_at';

    /** @param list<OrderLineResponse> $items */
    public function __construct(
        public string $id,
        public string $status,
        public array $items,
        public MoneyResponse $total,
        public string $reservationExpiresAt,
        public string $createdAt,
    ) {
    }

    public static function fromView(OrderView $order): self
    {
        return new self(
            $order->id->value,
            $order->status->value,
            array_map(OrderLineResponse::fromView(...), $order->lines),
            MoneyResponse::fromMoney($order->total),
            $order->reservationExpiresAt->format(DateTimeInterface::ATOM),
            $order->createdAt->format(DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            self::ID_KEY => $this->id,
            self::STATUS_KEY => $this->status,
            self::ITEMS_KEY => array_map(static fn (OrderLineResponse $item): array => $item->toArray(), $this->items),
            self::TOTAL_KEY => $this->total->toArray(),
            self::RESERVATION_EXPIRES_AT_KEY => $this->reservationExpiresAt,
            self::CREATED_AT_KEY => $this->createdAt,
        ];
    }
}
