<?php

declare(strict_types=1);

namespace App\Report\Response;

use App\Report\Query\ReportPeriod;
use App\Report\ReadModel\DailyRevenue;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;

final readonly class DailyRevenueResponse implements Arrayable
{
    private const string FROM_KEY = 'from';
    private const string TO_KEY = 'to';
    private const string CURRENCY_KEY = 'currency';
    private const string DAYS_KEY = 'days';
    private const string DAY_KEY = 'day';
    private const string ORDERS_KEY = 'orders';
    private const string REVENUE_KEY = 'revenue';
    private const string RUNNING_TOTAL_KEY = 'running_total';
    private const string DATE_FORMAT = 'Y-m-d';

    /** @param list<DailyRevenue> $days */
    public function __construct(public ReportPeriod $period, public array $days)
    {
    }

    /** @param list<DailyRevenue> $days */
    public static function fromRows(ReportPeriod $period, array $days): self
    {
        return new self($period, $days);
    }

    public function toArray(): array
    {
        return [
            self::FROM_KEY => $this->period->from->format(self::DATE_FORMAT),
            self::TO_KEY => $this->period->to->format(self::DATE_FORMAT),
            self::CURRENCY_KEY => $this->period->currency->getCode(),
            self::DAYS_KEY => array_map(static fn (DailyRevenue $day): array => [
                self::DAY_KEY => $day->day->format(self::DATE_FORMAT),
                self::ORDERS_KEY => $day->orders,
                self::REVENUE_KEY => MoneyResponse::fromMoney($day->revenue)->toArray(),
                self::RUNNING_TOTAL_KEY => MoneyResponse::fromMoney($day->runningTotal)->toArray(),
            ], $this->days),
        ];
    }
}
