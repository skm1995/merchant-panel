<?php

declare(strict_types=1);

namespace Tests\Unit\Cart\Request;

use App\Cart\Request\SetCartItemQuantityRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;

final class SetCartItemQuantityRequestTest extends TestCase
{
    private const string PATH = '/api/v1/carts/x/items/5';
    private const string VALID_BODY = '{"quantity":3}';
    private const string TEXT_QUANTITY_BODY = '{"quantity":"3"}';
    private const string MISSING_QUANTITY_BODY = '{"amount":3}';

    public function testReadsRouteParametersAndIntegerQuantity(): void
    {
        $command = SetCartItemQuantityRequest::fromRequest(self::request(self::VALID_BODY));

        self::assertSame(CartFixtures::CART_ID, $command->cartId->value);
        self::assertSame(5, $command->productId);
        self::assertSame(3, $command->quantity);
    }

    public function testRejectsMissingOrNonIntegerQuantity(): void
    {
        foreach ([self::TEXT_QUANTITY_BODY, self::MISSING_QUANTITY_BODY] as $body) {
            try {
                SetCartItemQuantityRequest::fromRequest(self::request($body));
                self::fail('Expected the request to be rejected.');
            } catch (InvalidRequestException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private static function request(string $body): Request
    {
        return new Request(HttpMethodEnum::PUT->value, self::PATH, body: $body)
            ->withRouteParameters(['id' => CartFixtures::CART_ID, 'productId' => '5']);
    }
}
