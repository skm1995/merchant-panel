<?php

declare(strict_types=1);

namespace App\Cart\Response;

use App\Cart\Entity\Cart;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;

final readonly class CartResponse implements Arrayable
{
    private const string ID_KEY = 'id';
    private const string STATUS_KEY = 'status';
    private const string ITEMS_KEY = 'items';
    private const string TOTAL_KEY = 'total';

    /** @param list<CartLineResponse> $items */
    public function __construct(
        public string $id,
        public string $status,
        public array $items,
        public ?MoneyResponse $total,
    ) {
    }

    public static function fromCart(Cart $cart): self
    {
        $total = $cart->total();

        return new self(
            $cart->id->value,
            $cart->status->value,
            array_map(CartLineResponse::fromLine(...), $cart->lines),
            $total === null ? null : MoneyResponse::fromMoney($total),
        );
    }

    public function toArray(): array
    {
        return [
            self::ID_KEY => $this->id,
            self::STATUS_KEY => $this->status,
            self::ITEMS_KEY => array_map(static fn (CartLineResponse $item): array => $item->toArray(), $this->items),
            self::TOTAL_KEY => $this->total?->toArray(),
        ];
    }
}
