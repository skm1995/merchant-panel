<?php

declare(strict_types=1);

namespace App\Report\Response;

use App\Report\ReadModel\MonthlyRevenue;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;
use Money\Currency;

final readonly class MonthlyRevenueResponse implements Arrayable
{
    private const string CURRENCY_KEY = 'currency';
    private const string MONTHS_KEY = 'months';
    private const string MONTH_KEY = 'month';
    private const string REVENUE_KEY = 'revenue';
    private const string PREVIOUS_YEAR_REVENUE_KEY = 'previous_year_revenue';
    private const string CHANGE_PERCENT_KEY = 'change_percent';

    /** @param list<MonthlyRevenue> $months */
    public function __construct(public Currency $currency, public array $months)
    {
    }

    public function toArray(): array
    {
        return [
            self::CURRENCY_KEY => $this->currency->getCode(),
            self::MONTHS_KEY => array_map(static fn (MonthlyRevenue $month): array => [
                self::MONTH_KEY => $month->month,
                self::REVENUE_KEY => MoneyResponse::fromMoney($month->revenue)->toArray(),
                self::PREVIOUS_YEAR_REVENUE_KEY => MoneyResponse::fromMoney($month->previousYearRevenue)->toArray(),
                self::CHANGE_PERCENT_KEY => $month->changePercent,
            ], $this->months),
        ];
    }
}
