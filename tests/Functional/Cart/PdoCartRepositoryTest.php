<?php

declare(strict_types=1);

namespace Tests\Functional\Cart;

use App\Cart\Entity\Cart;
use App\Cart\Entity\CartId;
use App\Cart\Enum\CartStatusEnum;
use App\Cart\Repository\PdoCartRepository;
use App\Shared\Database\DatabaseConnection;
use Money\Money;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestProductData;

final class PdoCartRepositoryTest extends TestCase
{
    private const string SKU_PREFIX = 'cart-repository-';
    private const int PRICE_IN_GROSZE = 1250;
    private const int STOCK = 10;

    private PDO $pdo;
    private PdoCartRepository $carts;
    private CartId $cartId;
    private string $prefix;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->carts = new PdoCartRepository($this->pdo);
        $this->cartId = CartId::generate();
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, [$this->cartId]);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testCreatesEmptyOpenCart(): void
    {
        $this->carts->create(Cart::open($this->cartId));

        $cart = $this->carts->find($this->cartId);

        self::assertNotNull($cart);
        self::assertSame($this->cartId->value, $cart->id->value);
        self::assertTrue($cart->isOpen());
        self::assertSame([], $cart->lines);
        self::assertSame(CartStatusEnum::OPEN, $this->carts->lockStatus($this->cartId));
    }

    public function testSavesReplacesAndRemovesItemsWithCurrentProductPrices(): void
    {
        [$first, $second] = TestProductData::create($this->pdo, $this->prefix, 2, self::PRICE_IN_GROSZE, self::STOCK);
        $this->carts->create(Cart::open($this->cartId));

        $this->carts->saveItem($this->cartId, $second, 1);
        $this->carts->saveItem($this->cartId, $first, 2);
        $this->carts->saveItem($this->cartId, $first, 3);
        $this->carts->removeItem($this->cartId, $second);
        $cart = $this->carts->find($this->cartId);

        self::assertNotNull($cart);
        self::assertCount(1, $cart->lines);
        self::assertSame(3, $cart->lines[0]->quantity);
        self::assertTrue(Money::PLN(3 * self::PRICE_IN_GROSZE)->equals($cart->total() ?? Money::PLN(0)));
    }

    public function testUpdatesStatus(): void
    {
        $this->carts->create(Cart::open($this->cartId));

        $this->carts->updateStatus($this->cartId, CartStatusEnum::CHECKED_OUT);

        self::assertSame(CartStatusEnum::CHECKED_OUT, $this->carts->find($this->cartId)?->status);
    }

    public function testUnknownCartIsMissing(): void
    {
        self::assertNull($this->carts->find($this->cartId));
        self::assertNull($this->carts->lockStatus($this->cartId));
    }
}
