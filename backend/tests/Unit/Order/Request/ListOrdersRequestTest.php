<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Request;

use App\Order\Request\ListOrdersRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;

final class ListOrdersRequestTest extends TestCase
{
    private const string PATH = '/api/v1/panel/orders';

    public function testStartsWithTheNewestPageOfDefaultSize(): void
    {
        $query = ListOrdersRequest::toQuery(new Request(HttpMethodEnum::GET->value, self::PATH));

        self::assertSame(ListOrdersRequest::DEFAULT_LIMIT, $query->limit);
        self::assertNull($query->after);
    }

    public function testReadsTheLimitAndCursor(): void
    {
        $query = ListOrdersRequest::toQuery(new Request(HttpMethodEnum::GET->value, self::PATH, ['limit' => '5', 'cursor' => OrderFixtures::ORDER_ID]));

        self::assertSame(5, $query->limit);
        self::assertEquals(OrderFixtures::orderId(), $query->after);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidParameters(): iterable
    {
        yield 'zero limit' => [['limit' => '0']];
        yield 'limit too large' => [['limit' => '101']];
        yield 'limit not a number' => [['limit' => 'ten']];
        yield 'cursor not an order id' => [['cursor' => 'not-a-uuid']];
    }

    /** @param array<string, string> $parameters */
    #[DataProvider('invalidParameters')]
    public function testRejectsInvalidParameters(array $parameters): void
    {
        $this->expectException(InvalidRequestException::class);

        ListOrdersRequest::toQuery(new Request(HttpMethodEnum::GET->value, self::PATH, $parameters));
    }
}
