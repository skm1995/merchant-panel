<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Request;

use App\Order\Enum\OrderStatusEnum;
use App\Report\Request\OrderReportRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;

final class OrderReportRequestTest extends TestCase
{
    private const string PATH = '/legacy/order-report.php';

    public function testKeepsTheLegacyDefaults(): void
    {
        $query = OrderReportRequest::toQuery(new Request(HttpMethodEnum::GET->value, self::PATH));

        self::assertSame(OrderStatusEnum::PAID, $query->status);
        self::assertSame('2000-01-01', $query->since->format('Y-m-d'));
    }

    public function testRejectsUnknownStatusesAndMalformedDates(): void
    {
        foreach ([['status' => "paid' OR '1'='1"], ['since' => '2000-01-01 OR 1']] as $parameters) {
            try {
                OrderReportRequest::toQuery(new Request(HttpMethodEnum::GET->value, self::PATH, $parameters));
                self::fail('Expected the parameters to be rejected.');
            } catch (InvalidRequestException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
