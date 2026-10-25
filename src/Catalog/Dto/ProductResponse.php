<?php

declare(strict_types=1);

namespace App\Catalog\Dto;

use App\Catalog\Entity\Product;
use App\Shared\Contract\Arrayable;

final readonly class ProductResponse implements Arrayable
{
    private const string ID_KEY = 'id';
    private const string SKU_KEY = 'sku';
    private const string NAME_KEY = 'name';
    private const string PRICE_KEY = 'price';
    private const string AMOUNT_KEY = 'amount';
    private const string CURRENCY_KEY = 'currency';
    private const string AVAILABLE_QUANTITY_KEY = 'available_quantity';

    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public string $priceAmount,
        public string $priceCurrency,
        public int $availableQuantity,
    ) {
    }

    public static function fromProduct(Product $product): self
    {
        return new self(
            $product->id,
            $product->sku,
            $product->name,
            $product->price->getAmount(),
            $product->price->getCurrency()->getCode(),
            $product->availableQuantity(),
        );
    }

    public function toArray(): array
    {
        return [
            self::ID_KEY => $this->id,
            self::SKU_KEY => $this->sku,
            self::NAME_KEY => $this->name,
            self::PRICE_KEY => [
                self::AMOUNT_KEY => $this->priceAmount,
                self::CURRENCY_KEY => $this->priceCurrency,
            ],
            self::AVAILABLE_QUANTITY_KEY => $this->availableQuantity,
        ];
    }
}
