<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Order\Entity\OrderId;
use App\Order\Enum\OrderStatusEnum;
use App\Order\ReadModel\OrderSummary;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use DateTimeImmutable;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<OrderSummary> */
#[HydratedRecord]
final readonly class OrderSummaryRecord implements EntityRecord
{
    public string $publicId;
    public string $status;
    public int $totalAmount;
    /** @var non-empty-string */
    public string $totalCurrency;
    public string $createdAt;

    public function toEntity(): OrderSummary
    {
        return new OrderSummary(
            OrderId::fromBinary($this->publicId),
            OrderStatusEnum::from($this->status),
            new Money($this->totalAmount, new Currency($this->totalCurrency)),
            new DateTimeImmutable($this->createdAt),
        );
    }
}
