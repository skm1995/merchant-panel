<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Report\ReadModel\ReportedOrder;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use DateTimeImmutable;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<ReportedOrder> */
#[HydratedRecord]
final readonly class ReportedOrderRecord implements EntityRecord
{
    public int $orderNumber;
    public ?string $products;
    public int $totalAmount;
    /** @var non-empty-string */
    public string $totalCurrency;
    public string $placedAt;

    public function toEntity(): ReportedOrder
    {
        return new ReportedOrder(
            $this->orderNumber,
            $this->products,
            new Money($this->totalAmount, new Currency($this->totalCurrency)),
            new DateTimeImmutable($this->placedAt),
        );
    }
}
