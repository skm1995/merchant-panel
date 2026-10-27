<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Request;

use App\Order\Request\OrderAccessTokenHeader;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Order\OrderFixtures;

final class OrderAccessTokenHeaderTest extends TestCase
{
    private const string PATH = '/api/v1/orders/x';

    public function testReadsTheTokenFromTheHeader(): void
    {
        $request = new Request(HttpMethodEnum::GET->value, self::PATH, headers: ['x-order-token' => OrderFixtures::accessToken()]);

        self::assertSame(OrderFixtures::accessToken(), OrderAccessTokenHeader::read($request));
    }

    public function testRequiresTheHeader(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Send the order access token from checkout in the X-Order-Token header.');

        OrderAccessTokenHeader::read(new Request(HttpMethodEnum::GET->value, self::PATH));
    }
}
