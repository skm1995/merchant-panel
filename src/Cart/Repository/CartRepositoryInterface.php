<?php

declare(strict_types=1);

namespace App\Cart\Repository;

use App\Cart\Entity\Cart;
use App\Cart\Entity\CartId;
use App\Cart\Enum\CartStatusEnum;

interface CartRepositoryInterface
{
    public function create(CartId $id): void;

    public function find(CartId $id): ?Cart;

    /**
     * Locks the cart row until the surrounding transaction ends and returns its status.
     */
    public function lockStatus(CartId $id): ?CartStatusEnum;

    public function updateStatus(CartId $id, CartStatusEnum $status): void;

    public function saveItem(CartId $cartId, int $productId, int $quantity): void;

    public function removeItem(CartId $cartId, int $productId): void;
}
