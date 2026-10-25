<?php

declare(strict_types=1);

namespace App\Catalog\Response;

use App\Catalog\Service\ProductPage;
use App\Shared\Contract\Arrayable;

final readonly class ProductPageResponse implements Arrayable
{
    private const string DATA_KEY = 'data';
    private const string NEXT_CURSOR_KEY = 'next_cursor';

    /** @param list<ProductResponse> $products */
    public function __construct(public array $products, public ?int $nextCursor)
    {
    }

    public static function fromPage(ProductPage $page): self
    {
        return new self(array_map(ProductResponse::fromProduct(...), $page->products), $page->nextCursor);
    }

    public function toArray(): array
    {
        return [
            self::DATA_KEY => array_map(static fn (ProductResponse $product): array => $product->toArray(), $this->products),
            self::NEXT_CURSOR_KEY => $this->nextCursor,
        ];
    }
}
