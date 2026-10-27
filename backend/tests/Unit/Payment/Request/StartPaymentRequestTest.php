<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Request;

use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Request\StartPaymentRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;
use Tests\Unit\Payment\PaymentFixtures;

final class StartPaymentRequestTest extends TestCase
{
    private const string PATH = '/api/v1/orders/x/payments';

    public function testReadsOrderProviderAndIdempotencyKey(): void
    {
        $command = StartPaymentRequest::toCommand(self::request('{"provider":"quickpay"}', true));

        self::assertSame(OrderFixtures::ORDER_ID, $command->orderId->value);
        self::assertSame(OrderFixtures::accessToken(), $command->orderAccessToken);
        self::assertSame(PaymentProviderEnum::QUICKPAY, $command->provider);
        self::assertSame(PaymentFixtures::IDEMPOTENCY_KEY, $command->idempotencyKey);
    }

    public function testRejectsUnknownProvider(): void
    {
        $this->expectException(InvalidRequestException::class);

        StartPaymentRequest::toCommand(self::request('{"provider":"cash"}', true));
    }

    public function testRequiresIdempotencyKey(): void
    {
        $this->expectException(InvalidRequestException::class);

        StartPaymentRequest::toCommand(self::request('{"provider":"fakepay"}', false));
    }

    private static function request(string $body, bool $withKey): Request
    {
        return new Request(
            HttpMethodEnum::POST->value,
            self::PATH,
            headers: [
                'x-order-token' => OrderFixtures::accessToken(),
                ...($withKey ? ['idempotency-key' => PaymentFixtures::IDEMPOTENCY_KEY] : []),
            ],
            body: $body,
        )->withRouteParameters(['id' => OrderFixtures::ORDER_ID]);
    }
}
