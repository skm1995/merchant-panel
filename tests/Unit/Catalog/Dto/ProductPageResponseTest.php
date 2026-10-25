<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Dto;

use App\Catalog\Dto\ProductPageResponse;
use App\Catalog\Service\ProductPage;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Catalog\ProductFactory;

final class ProductPageResponseTest extends TestCase
{
    private const int NEXT_CURSOR = 8;

    public function testWrapsProductsAndNextCursor(): void
    {
        $page = new ProductPage([ProductFactory::create(7), ProductFactory::create(8)], self::NEXT_CURSOR);

        $body = ProductPageResponse::fromPage($page)->toArray();

        self::assertSame([7, 8], array_column($body['data'], 'id'));
        self::assertSame(self::NEXT_CURSOR, $body['next_cursor']);
    }
}
