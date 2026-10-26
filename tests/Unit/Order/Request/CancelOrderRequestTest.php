<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Request;

use App\Order\Request\CancelOrderRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\PreconditionRequiredException;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;

final class CancelOrderRequestTest extends TestCase
{
    private const string PATH = '/api/v1/orders/x/cancel';

    public function testReadsOrderIdAndExpectedVersion(): void
    {
        $command = CancelOrderRequest::toCommand(self::request(['if-match' => '"4"']));

        self::assertSame(OrderFixtures::ORDER_ID, $command->orderId->value);
        self::assertSame(4, $command->expectedVersion);
    }

    public function testRequiresIfMatchPrecondition(): void
    {
        $this->expectException(PreconditionRequiredException::class);

        CancelOrderRequest::toCommand(self::request([]));
    }

    /** @param array<string, string> $headers */
    private static function request(array $headers): Request
    {
        return new Request(HttpMethodEnum::POST->value, self::PATH, headers: $headers)
            ->withRouteParameters(['id' => OrderFixtures::ORDER_ID]);
    }
}
