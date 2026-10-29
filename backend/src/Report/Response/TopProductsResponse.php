<?php

declare(strict_types=1);

namespace App\Report\Response;

use App\Report\ReadModel\TopProduct;
use App\Report\ValueObject\ReportPeriod;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;

final readonly class TopProductsResponse implements Arrayable
{
    private const string PRODUCTS_KEY = 'products';
    private const string RANK_KEY = 'rank';
    private const string PRODUCT_ID_KEY = 'product_id';
    private const string SKU_KEY = 'sku';
    private const string NAME_KEY = 'name';
    private const string UNITS_SOLD_KEY = 'units_sold';
    private const string REVENUE_KEY = 'revenue';

    /** @param list<TopProduct> $products */
    public function __construct(public ReportPeriod $period, public array $products)
    {
    }

    public function toArray(): array
    {
        return [
            ...ReportPeriodResponse::fromPeriod($this->period)->toArray(),
            self::PRODUCTS_KEY => array_map(static fn (TopProduct $product): array => [
                self::RANK_KEY => $product->rank,
                self::PRODUCT_ID_KEY => $product->productId,
                self::SKU_KEY => $product->sku,
                self::NAME_KEY => $product->name,
                self::UNITS_SOLD_KEY => $product->unitsSold,
                self::REVENUE_KEY => MoneyResponse::fromMoney($product->revenue)->toArray(),
            ], $this->products),
        ];
    }
}
