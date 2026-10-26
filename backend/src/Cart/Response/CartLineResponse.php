<?php

declare(strict_types=1);

namespace App\Cart\Response;

use App\Cart\Entity\CartLine;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;

final readonly class CartLineResponse implements Arrayable
{
    private const string PRODUCT_ID_KEY = 'product_id';
    private const string SKU_KEY = 'sku';
    private const string NAME_KEY = 'name';
    private const string QUANTITY_KEY = 'quantity';
    private const string UNIT_PRICE_KEY = 'unit_price';
    private const string LINE_TOTAL_KEY = 'line_total';

    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public int $quantity,
        public MoneyResponse $unitPrice,
        public MoneyResponse $lineTotal,
    ) {
    }

    public static function fromLine(CartLine $line): self
    {
        return new self(
            $line->productId,
            $line->sku,
            $line->name,
            $line->quantity,
            MoneyResponse::fromMoney($line->unitPrice),
            MoneyResponse::fromMoney($line->lineTotal()),
        );
    }

    public function toArray(): array
    {
        return [
            self::PRODUCT_ID_KEY => $this->productId,
            self::SKU_KEY => $this->sku,
            self::NAME_KEY => $this->name,
            self::QUANTITY_KEY => $this->quantity,
            self::UNIT_PRICE_KEY => $this->unitPrice->toArray(),
            self::LINE_TOTAL_KEY => $this->lineTotal->toArray(),
        ];
    }
}
