<?php

declare(strict_types=1);

namespace Tests\Functional\Order;

use App\Cart\Entity\CartId;
use App\Catalog\Repository\PdoProductRepository;
use App\Shared\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestCheckout;
use Tests\Functional\Support\TestProductData;

/**
 * Exercises the whole asynchronous path: the outbox relay publishes the delayed expiry,
 * RabbitMQ holds it until the short test reservation ends, and the worker expires the order.
 */
final class OrderExpiryTest extends TestCase
{
    private const string SKU_PREFIX = 'expiry-';
    private const string ORDER_PATH_FORMAT = '/api/v1/orders/%s';
    private const string EXPIRED = 'expired';
    private const int PRICE_IN_GROSZE = 1000;
    private const int STOCK = 4;
    private const int QUANTITY = 3;
    private const int WAIT_TIMEOUT_SECONDS = 20;
    private const int POLL_INTERVAL_MICROSECONDS = 250000;

    private PDO $pdo;
    private string $prefix;
    /** @var list<CartId> */
    private array $cartIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testUnpaidOrderExpiresAndReleasesItsStock(): void
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK);
        $order = TestCheckout::place($productId, self::QUANTITY);
        $this->cartIds[] = $order->cartId;
        $products = new PdoProductRepository($this->pdo);
        $reserved = $products->findById($productId);
        self::assertNotNull($reserved);
        self::assertSame(self::STOCK - self::QUANTITY, $reserved->availableQuantity());

        $status = $this->waitForStatus($order, self::EXPIRED);
        $released = new PdoProductRepository($this->pdo)->findById($productId);

        self::assertSame(self::EXPIRED, $status);
        self::assertSame(self::STOCK, $released?->availableQuantity());
    }

    private function waitForStatus(TestCheckout $order, string $expected): ?string
    {
        $deadline = microtime(true) + self::WAIT_TIMEOUT_SECONDS;

        do {
            $status = ApiClient::json(ApiClient::sendWithQuery(sprintf(self::ORDER_PATH_FORMAT, $order->orderId), [], $order->headers()))['status'] ?? null;

            if ($status === $expected) {
                return $status;
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        return is_string($status) ? $status : null;
    }

}
