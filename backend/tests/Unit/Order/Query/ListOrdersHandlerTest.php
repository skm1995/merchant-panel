<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Query;

use App\Order\Entity\OrderId;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Query\ListOrdersHandler;
use App\Order\Query\ListOrdersQuery;
use App\Order\ReadModel\OrderSummary;
use App\Order\Repository\OrderReadRepositoryInterface;
use DateTimeImmutable;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class ListOrdersHandlerTest extends TestCase
{
    private const int LIMIT = 2;

    public function testFetchesOneExtraOrderToFindTheNextPage(): void
    {
        $after = OrderId::generate();
        $summaries = [self::summary(), self::summary(), self::summary()];
        $orders = $this->createMock(OrderReadRepositoryInterface::class);
        $orders->expects(self::once())->method('findPage')->with(self::LIMIT + 1, $after)->willReturn($summaries);

        $page = new ListOrdersHandler($orders)->handle(new ListOrdersQuery(self::LIMIT, $after));

        self::assertSame([$summaries[0], $summaries[1]], $page->orders);
        self::assertSame($summaries[1]->id, $page->nextCursor);
    }

    public function testTheLastPageHasNoCursor(): void
    {
        $orders = $this->createStub(OrderReadRepositoryInterface::class);
        $orders->method('findPage')->willReturn([self::summary()]);

        self::assertNull(new ListOrdersHandler($orders)->handle(new ListOrdersQuery(self::LIMIT, null))->nextCursor);
    }

    private static function summary(): OrderSummary
    {
        return new OrderSummary(OrderId::generate(), OrderStatusEnum::PAID, Money::PLN(1000), new DateTimeImmutable());
    }
}
