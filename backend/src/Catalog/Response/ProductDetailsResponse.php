<?php

declare(strict_types=1);

namespace App\Catalog\Response;

use App\Catalog\Entity\Product;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;

/**
 * A product as the merchant panel edits it, including stock and reservations that the
 * public catalog does not show.
 */
final readonly class ProductDetailsResponse implements Arrayable
{
    private const string ID_KEY = 'id';
    private const string SKU_KEY = 'sku';
    private const string NAME_KEY = 'name';
    private const string PRICE_KEY = 'price';
    private const string STOCK_KEY = 'stock';
    private const string RESERVED_KEY = 'reserved';

    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public MoneyResponse $price,
        public int $stock,
        public int $reserved,
    ) {
    }

    public static function fromProduct(Product $product): self
    {
        return new self($product->id, $product->sku, $product->name, MoneyResponse::fromMoney($product->price), $product->stock, $product->reserved);
    }

    public function toArray(): array
    {
        return [
            self::ID_KEY => $this->id,
            self::SKU_KEY => $this->sku,
            self::NAME_KEY => $this->name,
            self::PRICE_KEY => $this->price->toArray(),
            self::STOCK_KEY => $this->stock,
            self::RESERVED_KEY => $this->reserved,
        ];
    }
}
