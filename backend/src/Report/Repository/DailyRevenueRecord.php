<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Report\ReadModel\DailyRevenue;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use DateTimeImmutable;
use Money\Currency;
use Money\Money;

/** @implements EntityRecord<DailyRevenue> */
#[HydratedRecord]
final readonly class DailyRevenueRecord implements EntityRecord
{
    public string $day;
    public int $orders;
    /** @var numeric-string SUM over BIGINT returns DECIMAL, which PDO reads as a string */
    public string $revenue;
    /** @var numeric-string */
    public string $runningTotal;
    /** @var non-empty-string */
    public string $currency;

    public function toEntity(): DailyRevenue
    {
        $currency = new Currency($this->currency);

        return new DailyRevenue(
            new DateTimeImmutable($this->day),
            $this->orders,
            new Money($this->revenue, $currency),
            new Money($this->runningTotal, $currency),
        );
    }
}
