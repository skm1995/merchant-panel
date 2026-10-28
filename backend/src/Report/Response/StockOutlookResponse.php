<?php

declare(strict_types=1);

namespace App\Report\Response;

use App\Report\ReadModel\StockOutlook;
use App\Shared\Contract\Arrayable;

final readonly class StockOutlookResponse implements Arrayable
{
    private const string DAYS_MEASURED_KEY = 'days_measured';
    private const string PRODUCTS_KEY = 'products';
    private const string PRODUCT_ID_KEY = 'product_id';
    private const string SKU_KEY = 'sku';
    private const string NAME_KEY = 'name';
    private const string AVAILABLE_KEY = 'available';
    private const string UNITS_SOLD_KEY = 'units_sold';
    private const string UNITS_PER_DAY_KEY = 'units_per_day';
    private const string DAYS_LEFT_KEY = 'days_left';

    /** @param list<StockOutlook> $products */
    public function __construct(public int $daysMeasured, public array $products)
    {
    }

    public function toArray(): array
    {
        return [
            self::DAYS_MEASURED_KEY => $this->daysMeasured,
            self::PRODUCTS_KEY => array_map(static fn (StockOutlook $product): array => [
                self::PRODUCT_ID_KEY => $product->productId,
                self::SKU_KEY => $product->sku,
                self::NAME_KEY => $product->name,
                self::AVAILABLE_KEY => $product->available,
                self::UNITS_SOLD_KEY => $product->unitsSold,
                self::UNITS_PER_DAY_KEY => $product->unitsPerDay,
                self::DAYS_LEFT_KEY => $product->daysLeft,
            ], $this->products),
        ];
    }
}
