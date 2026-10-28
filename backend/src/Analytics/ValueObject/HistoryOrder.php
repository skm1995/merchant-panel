<?php

declare(strict_types=1);

namespace App\Analytics\ValueObject;

use App\Order\Enum\OrderStatusEnum;
use DateTimeImmutable;
use Money\Money;

final readonly class HistoryOrder
{
    /**
     * @param string $publicId 16 bytes of a version 4 UUID
     * @param string $cartId 16 bytes of a version 4 UUID
     * @param non-empty-list<HistoryLine> $lines
     */
    public function __construct(
        public string $publicId,
        public string $cartId,
        public string $idempotencyKey,
        public OrderStatusEnum $status,
        public DateTimeImmutable $placedAt,
        public array $lines,
    ) {
    }

    public function total(): Money
    {
        return Money::sum(...array_map(static fn (HistoryLine $line): Money => $line->lineTotal(), $this->lines));
    }
}
