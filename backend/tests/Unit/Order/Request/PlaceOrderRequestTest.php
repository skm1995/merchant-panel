<?php

declare(strict_types=1);

namespace Tests\Unit\Order\Request;

use App\Order\Request\PlaceOrderRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\IdempotencyKey;
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
        $command = PlaceOrderRequest::toCommand(self::request(['idempotency-key' => OrderFixtures::IDEMPOTENCY_KEY]), fromSimulator: false);

        self::assertSame(CartFixtures::CART_ID, $command->cartId->value);
        self::assertSame(OrderFixtures::IDEMPOTENCY_KEY, $command->idempotencyKey);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidHeaders(): iterable
    {
        yield 'missing' => [[]];
        yield 'too long' => [['idempotency-key' => str_repeat('k', IdempotencyKey::MAXIMUM_LENGTH + 1)]];
        yield 'control characters' => [['idempotency-key' => "key\twith-tab"]];
    }

    /** @param array<string, string> $headers */
    #[DataProvider('invalidHeaders')]
    public function testRequiresAPrintableIdempotencyKey(array $headers): void
    {
        $this->expectException(InvalidRequestException::class);

        PlaceOrderRequest::toCommand(self::request($headers), fromSimulator: false);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function reservedKeys(): iterable
    {
        yield 'history, from a customer' => ['history:1', false];
        yield 'history, even from the simulator' => ['history:1', true];
        yield 'simulation, from a customer' => ['simulation:run:1-1', false];
    }

    #[DataProvider('reservedKeys')]
    public function testRefusesKeysReservedForTheShopsOwnOrders(string $key, bool $fromSimulator): void
    {
        $this->expectException(InvalidRequestException::class);

        PlaceOrderRequest::toCommand(self::request(['idempotency-key' => $key]), $fromSimulator);
    }

    public function testLetsTheSimulatorUseItsReservedKeys(): void
    {
        $command = PlaceOrderRequest::toCommand(self::request(['idempotency-key' => 'simulation:run:1-1']), fromSimulator: true);

        self::assertSame('simulation:run:1-1', $command->idempotencyKey);
    }

    /** @param array<string, string> $headers */
    private static function request(array $headers): Request
    {
        return new Request(HttpMethodEnum::POST->value, self::PATH, headers: $headers)
            ->withRouteParameters(['id' => CartFixtures::CART_ID]);
    }
}
