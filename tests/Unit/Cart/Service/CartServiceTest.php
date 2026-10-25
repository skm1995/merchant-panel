<?php

declare(strict_types=1);

namespace Tests\Unit\Cart\Service;

use App\Cart\Entity\CartId;
use App\Cart\Enum\CartStatusEnum;
use App\Cart\Exception\CartNotFoundException;
use App\Cart\Exception\CartNotOpenException;
use App\Cart\Exception\InvalidCartQuantityException;
use App\Cart\Repository\CartRepositoryInterface;
use App\Cart\Service\CartService;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\ProductCatalog;
use App\Shared\Database\Enum\IsolationLevelEnum;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;
use Tests\Unit\Catalog\ProductFactory;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class CartServiceTest extends TestCase
{
    private const int PRODUCT_ID = 5;
    private const int QUANTITY = 2;

    private ImmediateTransactionRunner $transactions;

    protected function setUp(): void
    {
        $this->transactions = new ImmediateTransactionRunner();
    }

    public function testCreatesAndPersistsAnEmptyOpenCart(): void
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->expects(self::once())->method('create')->with(self::isInstanceOf(CartId::class));

        $cart = $this->service($carts)->create();

        self::assertTrue($cart->isOpen());
        self::assertSame([], $cart->lines);
    }

    public function testReportsMissingCart(): void
    {
        $carts = $this->createStub(CartRepositoryInterface::class);
        $carts->method('find')->willReturn(null);

        $this->expectException(CartNotFoundException::class);
        $this->service($carts)->get(CartFixtures::cartId());
    }

    public function testSavesItemQuantityWhileHoldingTheCartLock(): void
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->expects(self::once())->method('lockStatus')->willReturn(CartStatusEnum::OPEN);
        $carts->expects(self::once())->method('saveItem')->with(CartFixtures::cartId(), self::PRODUCT_ID, self::QUANTITY);
        $carts->expects(self::never())->method('removeItem');
        $carts->method('find')->willReturn(CartFixtures::cart([CartFixtures::line(self::PRODUCT_ID, self::QUANTITY, 100)]));

        $cart = $this->service($carts)->setItemQuantity(CartFixtures::cartId(), self::PRODUCT_ID, self::QUANTITY);

        self::assertSame(self::QUANTITY, $cart->lines[0]->quantity);
        self::assertSame(IsolationLevelEnum::READ_COMMITTED, $this->transactions->lastIsolationLevel);
    }

    public function testZeroQuantityRemovesTheItem(): void
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->method('lockStatus')->willReturn(CartStatusEnum::OPEN);
        $carts->expects(self::once())->method('removeItem')->with(CartFixtures::cartId(), self::PRODUCT_ID);
        $carts->expects(self::never())->method('saveItem');
        $carts->method('find')->willReturn(CartFixtures::cart());

        $this->service($carts)->setItemQuantity(CartFixtures::cartId(), self::PRODUCT_ID, 0);
    }

    public function testRejectsQuantityOutsideTheAllowedRange(): void
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->expects(self::never())->method('lockStatus');

        $this->expectException(InvalidCartQuantityException::class);
        $this->service($carts)->setItemQuantity(
            CartFixtures::cartId(),
            self::PRODUCT_ID,
            CartService::MAXIMUM_QUANTITY + 1,
        );
    }

    public function testRejectsUnknownProduct(): void
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->expects(self::never())->method('saveItem');

        $this->expectException(ProductNotFoundException::class);
        $this->service($carts, productExists: false)->setItemQuantity(CartFixtures::cartId(), self::PRODUCT_ID, self::QUANTITY);
    }

    public function testRejectsChangesToCheckedOutCart(): void
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->method('lockStatus')->willReturn(CartStatusEnum::CHECKED_OUT);
        $carts->expects(self::never())->method('saveItem');

        $this->expectException(CartNotOpenException::class);
        $this->service($carts)->setItemQuantity(CartFixtures::cartId(), self::PRODUCT_ID, self::QUANTITY);
    }

    public function testRejectsChangesToMissingCart(): void
    {
        $carts = $this->createStub(CartRepositoryInterface::class);
        $carts->method('lockStatus')->willReturn(null);

        $this->expectException(CartNotFoundException::class);
        $this->service($carts)->setItemQuantity(CartFixtures::cartId(), self::PRODUCT_ID, self::QUANTITY);
    }

    private function service(CartRepositoryInterface $carts, bool $productExists = true): CartService
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findById')->willReturn($productExists ? ProductFactory::create(self::PRODUCT_ID) : null);

        return new CartService($carts, new ProductCatalog($products), $this->transactions);
    }
}
