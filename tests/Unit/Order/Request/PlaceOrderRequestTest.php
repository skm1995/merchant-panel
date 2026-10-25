<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Request;

use App\Order\Request\PlaceOrderRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;
use Tests\Unit\Order\OrderFixtures;

final class PlaceOrderRequestTest extends TestCase
{
    private const string PATH = '/api/v1/carts/x/checkout';

    public function testBuildsCommandFromCartIdAndIdempotencyKey(): void
    {
        $command = PlaceOrderRequest::fromRequest(self::request(['idempotency-key' => OrderFixtures::IDEMPOTENCY_KEY]))
            ->toCommand();

        self::assertSame(CartFixtures::CART_ID, $command->cartId->value);
        self::assertSame(OrderFixtures::IDEMPOTENCY_KEY, $command->idempotencyKey);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidHeaders(): iterable
    {
        yield 'missing' => [[]];
        yield 'too long' => [['idempotency-key' => str_repeat('k', PlaceOrderRequest::MAXIMUM_KEY_LENGTH + 1)]];
        yield 'control characters' => [['idempotency-key' => "key\twith-tab"]];
    }

    /** @param array<string, string> $headers */
    #[DataProvider('invalidHeaders')]
    public function testRequiresAPrintableIdempotencyKey(array $headers): void
    {
        $this->expectException(InvalidRequestException::class);

        PlaceOrderRequest::fromRequest(self::request($headers));
    }

    /** @param array<string, string> $headers */
    private static function request(array $headers): Request
    {
        return new Request(HttpMethodEnum::POST->value, self::PATH, headers: $headers)
            ->withRouteParameters(['id' => CartFixtures::CART_ID]);
    }
}
