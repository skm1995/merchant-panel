<?php

declare(strict_types=1);

namespace App\Report\Response;

use App\Order\Enum\OrderStatusEnum;
use App\Report\ReadModel\ReportedOrder;
use App\Report\Service\ReportDate;
use App\Shared\Contract\Arrayable;
use DateTimeImmutable;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;

/**
 * Presentation of the order report page, keeping the texts and number format of the
 * legacy report it replaced. Amounts are formatted from decimal strings, never floats.
 */
final readonly class OrderReportPage implements Arrayable
{
    private const string HEADING_FORMAT = 'Orders with status %s since %s';
    private const string SUMMARY_FORMAT = 'Orders: %d, total: %s';
    private const string AMOUNT_WITH_CURRENCY_FORMAT = '%s %s';
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const string DECIMAL_POINT = '.';
    private const string THOUSANDS_SEPARATOR = ',';
    private const int THOUSANDS_GROUP = 3;
    private const string HEADING_KEY = 'heading';
    private const string ROWS_KEY = 'rows';
    private const string SUMMARY_KEY = 'summary';
    private const string ZERO_AMOUNT = '0.00';

    /** @param list<array{string, ?string, string, string}> $rows */
    public function __construct(public string $heading, public array $rows, public string $summary)
    {
    }

    /** @param list<ReportedOrder> $orders */
    public static function fromOrders(OrderStatusEnum $status, DateTimeImmutable $since, array $orders): self
    {
        $totals = array_map(static fn (ReportedOrder $order): Money => $order->total, $orders);

        return new self(
            sprintf(self::HEADING_FORMAT, $status->value, ReportDate::format($since)),
            array_map(static fn (ReportedOrder $order): array => [
                (string) $order->orderNumber,
                $order->products,
                sprintf(self::AMOUNT_WITH_CURRENCY_FORMAT, self::amount($order->total), $order->total->getCurrency()->getCode()),
                $order->placedAt->format(self::DATETIME_FORMAT),
            ], $orders),
            sprintf(self::SUMMARY_FORMAT, count($orders), $totals === [] ? self::ZERO_AMOUNT : self::amount(Money::sum(...$totals))),
        );
    }

    public function toArray(): array
    {
        return [self::HEADING_KEY => $this->heading, self::ROWS_KEY => $this->rows, self::SUMMARY_KEY => $this->summary];
    }

    private static function amount(Money $money): string
    {
        [$units, $fraction] = explode(self::DECIMAL_POINT, new DecimalMoneyFormatter(new ISOCurrencies())->format($money));
        $groups = str_split(strrev($units), self::THOUSANDS_GROUP);

        return strrev(implode(self::THOUSANDS_SEPARATOR, $groups)) . self::DECIMAL_POINT . $fraction;
    }
}
