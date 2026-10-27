<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Request;

use App\Order\Request\CancelOrderRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Exception\PreconditionRequiredException;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;

final class CancelOrderRequestTest extends TestCase
{
    private const string PATH = '/api/v1/orders/x/cancel';

    public function testReadsOrderIdAndExpectedVersion(): void
    {
        $command = CancelOrderRequest::toCommand(self::request(['if-match' => '"4"', 'x-order-token' => OrderFixtures::accessToken()]));

        self::assertSame(OrderFixtures::ORDER_ID, $command->orderId->value);
        self::assertSame(OrderFixtures::accessToken(), $command->accessToken);
        self::assertSame(4, $command->expectedVersion);
    }

    public function testRequiresIfMatchPrecondition(): void
    {
        $this->expectException(PreconditionRequiredException::class);

        CancelOrderRequest::toCommand(self::request(['x-order-token' => OrderFixtures::accessToken()]));
    }

    public function testRequiresTheOrderAccessToken(): void
    {
        $this->expectException(InvalidRequestException::class);

        CancelOrderRequest::toCommand(self::request(['if-match' => '"4"']));
    }

    /** @param array<string, string> $headers */
    private static function request(array $headers): Request
    {
        return new Request(HttpMethodEnum::POST->value, self::PATH, headers: $headers)
            ->withRouteParameters(['id' => OrderFixtures::ORDER_ID]);
    }
}
