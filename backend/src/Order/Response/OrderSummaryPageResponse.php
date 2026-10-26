<?php

declare(strict_types=1);

namespace App\Order\Response;

use App\Order\ReadModel\OrderSummary;
use App\Order\ReadModel\OrderSummaryPage;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;
use DateTimeInterface;

final readonly class OrderSummaryPageResponse implements Arrayable
{
    private const string DATA_KEY = 'data';
    private const string NEXT_CURSOR_KEY = 'next_cursor';
    private const string ID_KEY = 'id';
    private const string STATUS_KEY = 'status';
    private const string TOTAL_KEY = 'total';
    private const string CREATED_AT_KEY = 'created_at';

    /** @param list<array<string, mixed>> $orders */
    public function __construct(public array $orders, public ?string $nextCursor)
    {
    }

    public static function fromPage(OrderSummaryPage $page): self
    {
        return new self(
            array_map(static fn (OrderSummary $order): array => [
                self::ID_KEY => $order->id->value,
                self::STATUS_KEY => $order->status->value,
                self::TOTAL_KEY => MoneyResponse::fromMoney($order->total)->toArray(),
                self::CREATED_AT_KEY => $order->createdAt->format(DateTimeInterface::ATOM),
            ], $page->orders),
            $page->nextCursor?->value,
        );
    }

    public function toArray(): array
    {
        return [self::DATA_KEY => $this->orders, self::NEXT_CURSOR_KEY => $this->nextCursor];
    }
}
