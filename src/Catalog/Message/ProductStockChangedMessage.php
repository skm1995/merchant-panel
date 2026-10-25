<?php

declare(strict_types=1);

namespace App\Catalog\Message;

use App\Shared\Messaging\Message;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;

/**
 * Recorded through the outbox whenever stock or reservations of products change, so cached
 * product data is invalidated only after the change is committed.
 */
#[Message(transport: 'catalog')]
final readonly class ProductStockChangedMessage implements OutboxMessageInterface
{
    private const string TYPE = 'catalog.product_stock_changed';
    private const string PRODUCT_IDS_KEY = 'product_ids';

    /** @param list<int> $productIds */
    public function __construct(public array $productIds)
    {
    }

    public static function messageType(): string
    {
        return self::TYPE;
    }

    public static function fromArray(array $data): static
    {
        $productIds = $data[self::PRODUCT_IDS_KEY] ?? [];

        return new self(array_values(array_map(intval(...), is_array($productIds) ? $productIds : [])));
    }

    public function toArray(): array
    {
        return [self::PRODUCT_IDS_KEY => $this->productIds];
    }
}
