<?php

declare(strict_types=1);

namespace App\Report\Response;

use App\Report\ReadModel\DailyRevenue;
use App\Report\ValueObject\ReportDate;
use App\Report\ValueObject\ReportPeriod;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;

final readonly class DailyRevenueResponse implements Arrayable
{
    private const string DAYS_KEY = 'days';
    private const string DAY_KEY = 'day';
    private const string ORDERS_KEY = 'orders';
    private const string REVENUE_KEY = 'revenue';
    private const string RUNNING_TOTAL_KEY = 'running_total';

    /** @param list<DailyRevenue> $days */
    public function __construct(public ReportPeriod $period, public array $days)
    {
    }

    public function toArray(): array
    {
        return [
            ...ReportPeriodResponse::fromPeriod($this->period)->toArray(),
            self::DAYS_KEY => array_map(static fn (DailyRevenue $day): array => [
                self::DAY_KEY => ReportDate::format($day->day),
                self::ORDERS_KEY => $day->orders,
                self::REVENUE_KEY => MoneyResponse::fromMoney($day->revenue)->toArray(),
                self::RUNNING_TOTAL_KEY => MoneyResponse::fromMoney($day->runningTotal)->toArray(),
            ], $this->days),
        ];
    }
}
