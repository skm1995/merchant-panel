<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Service;

use App\Catalog\Exception\InvalidPageSizeException;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Catalog\Service\ProductCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Catalog\ProductFactory;

final class ProductCatalogTest extends TestCase
{
    private const int PAGE_SIZE = 2;
    private const int AFTER_ID = 10;
    private const int PRODUCT_ID = 11;

    public function testFetchesOneExtraProductToDetectTheNextPage(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::once())
            ->method('findPage')
            ->with(self::PAGE_SIZE + 1, self::AFTER_ID)
            ->willReturn([ProductFactory::create(11), ProductFactory::create(12), ProductFactory::create(13)]);

        $page = new ProductCatalog($products)->page(self::PAGE_SIZE, self::AFTER_ID);

        self::assertSame([11, 12], array_map(static fn ($product): int => $product->id, $page->products));
        self::assertSame(12, $page->nextCursor);
    }

    public function testLastPageHasNoNextCursor(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findPage')->willReturn([ProductFactory::create(11)]);

        self::assertNull(new ProductCatalog($products)->page(self::PAGE_SIZE, null)->nextCursor);
    }

    public function testRejectsPageSizeAboveMaximum(): void
    {
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->expects(self::never())->method('findPage');

        $this->expectException(InvalidPageSizeException::class);
        new ProductCatalog($products)->page(ProductCatalog::MAXIMUM_PAGE_SIZE + 1, null);
    }

    public function testReportsMissingProduct(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findById')->willReturn(null);

        $this->expectException(ProductNotFoundException::class);
        new ProductCatalog($products)->product(self::PRODUCT_ID);
    }
}
