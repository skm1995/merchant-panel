<?php

declare(strict_types=1);

namespace Tests\Functional\Order;

use App\Cart\Entity\Cart;
use App\Cart\Entity\CartId;
use App\Cart\Repository\PdoCartRepository;
use App\Order\Entity\Order;
use App\Order\Entity\OrderId;
use App\Order\Entity\OrderLine;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Exception\IdempotencyKeyReusedException;
use App\Order\Repository\PdoOrderReadRepository;
use App\Order\Repository\PdoOrderRepository;
use App\Shared\Database\DatabaseConnection;
use DateTimeImmutable;
use Money\Money;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestProductData;

final class PdoOrderRepositoryTest extends TestCase
{
    private const string SKU_PREFIX = 'order-repository-';
    private const string KEY_PREFIX = 'order-repository-key-';
    private const string EXPIRES_AT = '2026-10-25 12:15:00';
    private const int PRICE_IN_GROSZE = 1500;
    private const int STOCK = 10;

    private PDO $pdo;
    private PdoOrderRepository $orders;
    private string $prefix;
    /** @var list<CartId> */
    private array $cartIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->orders = new PdoOrderRepository($this->pdo);
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testStoresOrderAndReadsItBackAsView(): void
    {
        $order = $this->order(uniqid(self::KEY_PREFIX, true));

        $this->orders->add($order);
        $placement = $this->orders->findPlacement($order->idempotencyKey);
        $view = new PdoOrderReadRepository($this->pdo)->find($order->id);

        self::assertNotNull($placement);
        self::assertTrue($placement->orderId->equals($order->id));
        self::assertTrue($placement->cartId->equals($order->cartId));
        self::assertNotNull($view);
        self::assertSame(OrderStatusEnum::PENDING_PAYMENT, $view->status);
        self::assertTrue(Money::PLN(2 * self::PRICE_IN_GROSZE)->equals($view->total));
        self::assertSame(2, $view->lines[0]->quantity);
        self::assertEquals(new DateTimeImmutable(self::EXPIRES_AT), $view->reservationExpiresAt);
    }

    public function testRejectsIdempotencyKeyAlreadyUsedByAnotherOrder(): void
    {
        $key = uniqid(self::KEY_PREFIX, true);
        $this->orders->add($this->order($key));

        $this->expectException(IdempotencyKeyReusedException::class);
        $this->orders->add($this->order($key));
    }

    public function testUnknownOrderHasNoView(): void
    {
        self::assertNull(new PdoOrderReadRepository($this->pdo)->find(OrderId::generate()));
    }

    private function order(string $idempotencyKey): Order
    {
        [$productId] = TestProductData::create($this->pdo, $this->prefix . count($this->cartIds), 1, self::PRICE_IN_GROSZE, self::STOCK);
        $cartId = CartId::generate();
        new PdoCartRepository($this->pdo)->add(Cart::open($cartId));
        $this->cartIds[] = $cartId;

        return Order::place(
            OrderId::generate(),
            $cartId,
            $idempotencyKey,
            [new OrderLine($productId, 'SKU', 'Product', 2, Money::PLN(self::PRICE_IN_GROSZE))],
            new DateTimeImmutable(self::EXPIRES_AT),
        );
    }
}
