<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Cart\Entity\CartId;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\HttpMethodEnum;
use PHPUnit\Framework\Assert;

/**
 * An order placed through the API as a guest would: a new cart with one product, checked
 * out, with the access token the guest needs for later requests about the order.
 */
final readonly class TestCheckout
{
    private const string CARTS_PATH = '/api/v1/carts';
    private const string ITEM_PATH_FORMAT = '/api/v1/carts/%s/items/%d';
    private const string CHECKOUT_PATH_FORMAT = '/api/v1/carts/%s/checkout';
    private const string ID_KEY = 'id';
    private const string QUANTITY_KEY = 'quantity';
    private const string KEY_PREFIX = 'test-checkout-';

    private function __construct(public CartId $cartId, public string $orderId, public string $accessToken)
    {
    }

    public static function place(int $productId, int $quantity): self
    {
        $cartId = ApiClient::json(ApiClient::send(HttpMethodEnum::POST, self::CARTS_PATH))[self::ID_KEY];
        Assert::assertIsString($cartId);
        ApiClient::send(HttpMethodEnum::PUT, sprintf(self::ITEM_PATH_FORMAT, $cartId, $productId), [self::QUANTITY_KEY => $quantity]);
        $checkout = ApiClient::send(
            HttpMethodEnum::POST,
            sprintf(self::CHECKOUT_PATH_FORMAT, $cartId),
            headers: [HeaderNameEnum::IDEMPOTENCY_KEY->value => uniqid(self::KEY_PREFIX, true)],
        );
        $orderId = ApiClient::json($checkout)[self::ID_KEY];
        Assert::assertIsString($orderId);

        return new self(CartId::fromString($cartId), $orderId, $checkout->getHeaderLine(HeaderNameEnum::ORDER_TOKEN->value));
    }

    /**
     * The header that proves access to the order, merged with any others the request needs.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function headers(array $headers = []): array
    {
        return [HeaderNameEnum::ORDER_TOKEN->value => $this->accessToken, ...$headers];
    }
}
