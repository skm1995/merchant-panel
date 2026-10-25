<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Message;

use App\Catalog\Message\ProductStockChangedMessage;
use PHPUnit\Framework\TestCase;

final class ProductStockChangedMessageTest extends TestCase
{
    public function testTravelsThroughTheOutboxUnderAStableTypeName(): void
    {
        $restored = ProductStockChangedMessage::fromArray(new ProductStockChangedMessage([3, 8])->toArray());

        self::assertSame('catalog.product_stock_changed', ProductStockChangedMessage::messageType());
        self::assertSame([3, 8], $restored->productIds);
    }
}
