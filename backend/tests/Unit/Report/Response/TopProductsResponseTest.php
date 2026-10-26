<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Response;

use App\Report\Response\TopProductsResponse;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class TopProductsResponseTest extends TestCase
{
    public function testDescribesRankedProducts(): void
    {
        $body = new TopProductsResponse(ReportFixtures::period(), [ReportFixtures::product(1, 7, 4, 8000)])->toArray();

        self::assertSame(['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO, 'currency' => 'PLN'], array_slice($body, 0, 3));
        self::assertSame([
            'rank' => 1,
            'product_id' => 7,
            'sku' => 'SKU-7',
            'name' => 'Product 7',
            'units_sold' => 4,
            'revenue' => ['amount' => '8000', 'currency' => 'PLN'],
        ], $body['products'][0]);
    }
}
