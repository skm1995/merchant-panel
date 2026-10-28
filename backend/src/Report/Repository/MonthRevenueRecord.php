<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Report\ReadModel\MonthRevenue;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<MonthRevenue> */
#[HydratedRecord]
final readonly class MonthRevenueRecord implements EntityRecord
{
    public string $month;
    /** @var numeric-string */
    public string $revenue;
    /** @var non-empty-string */
    public string $currency;

    public function toEntity(): MonthRevenue
    {
        return new MonthRevenue($this->month, new Money($this->revenue, new Currency($this->currency)));
    }
}
