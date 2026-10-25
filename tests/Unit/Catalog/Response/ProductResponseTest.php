<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Response;

use App\Catalog\Response\ProductResponse;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Catalog\ProductFactory;

final class ProductResponseTest extends TestCase
{
    private const int PRODUCT_ID = 7;

    public function testExposesPublicFieldsWithMoneyInMinorUnits(): void
    {
        $response = ProductResponse::fromProduct(ProductFactory::create(self::PRODUCT_ID, 10, 3));

        self::assertSame([
            'id' => self::PRODUCT_ID,
            'sku' => 'SKU-7',
            'name' => 'Product 7',
            'price' => ['amount' => '1999', 'currency' => 'PLN'],
            'available_quantity' => 7,
        ], $response->toArray());
    }
}
