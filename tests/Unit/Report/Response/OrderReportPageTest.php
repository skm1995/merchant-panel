<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Response;

use App\Order\Enum\OrderStatusEnum;
use App\Report\Query\OrderReportQuery;
use App\Report\ReadModel\ReportedOrder;
use App\Report\Response\OrderReportPage;
use DateTimeImmutable;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class OrderReportPageTest extends TestCase
{
    public function testFormatsAmountsLikeTheLegacyReportWithoutFloats(): void
    {
        $page = OrderReportPage::fromOrders(
            new OrderReportQuery(OrderStatusEnum::PAID, new DateTimeImmutable('1999-01-01')),
            [
                new ReportedOrder(12, 'Mug', Money::PLN(123456), new DateTimeImmutable('1999-05-01 10:00:00')),
                new ReportedOrder(13, null, Money::PLN(5), new DateTimeImmutable('1999-05-02 10:00:00')),
                new ReportedOrder(14, 'Plate', Money::PLN(123456789012), new DateTimeImmutable('1999-05-03 10:00:00')),
            ],
        );

        self::assertSame('Orders with status paid since 1999-01-01', $page->heading);
        self::assertSame(['12', 'Mug', '1,234.56 PLN', '1999-05-01 10:00:00'], $page->rows[0]);
        self::assertSame(['13', null, '0.05 PLN', '1999-05-02 10:00:00'], $page->rows[1]);
        self::assertSame('1,234,567,890.12 PLN', $page->rows[2][2]);
        self::assertSame('Orders: 3, total: 1,234,569,124.73', $page->summary);
    }

    public function testSummarizesAnEmptyReport(): void
    {
        $page = OrderReportPage::fromOrders(new OrderReportQuery(OrderStatusEnum::EXPIRED, new DateTimeImmutable('1999-01-01')), []);

        self::assertSame([], $page->rows);
        self::assertSame('Orders: 0, total: 0.00', $page->summary);
    }
}
