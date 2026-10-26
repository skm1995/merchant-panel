<?php

declare(strict_types=1);

namespace App\Cart\Service;

use App\Cart\Entity\Cart;
use App\Cart\Entity\CartId;
use App\Cart\Enum\CartStatusEnum;
use App\Cart\Exception\CartNotFoundException;
use App\Cart\Exception\CartNotOpenException;
use App\Cart\Exception\InvalidCartQuantityException;
use App\Cart\Repository\CartRepositoryInterface;
use App\Catalog\Service\ProductCatalog;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;

final readonly class CartService
{
    public const int MAXIMUM_QUANTITY = 99;

    public function __construct(
        private CartRepositoryInterface $carts,
        private ProductCatalog $catalog,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function create(): Cart
    {
        $cart = Cart::open(CartId::generate());
        $this->carts->create($cart);

        return $cart;
    }

    public function get(CartId $id): Cart
    {
        return $this->carts->find($id) ?? throw new CartNotFoundException();
    }

    /**
     * Sets the quantity of a product in an open cart; zero removes the product.
     */
    public function setItemQuantity(CartId $cartId, int $productId, int $quantity): Cart
    {
        if ($quantity < 0 || $quantity > self::MAXIMUM_QUANTITY) {
            throw new InvalidCartQuantityException(self::MAXIMUM_QUANTITY);
        }

        $this->catalog->product($productId);
        $this->transactions->run(function () use ($cartId, $productId, $quantity): void {
            $status = $this->carts->lockStatus($cartId) ?? throw new CartNotFoundException();

            if ($status !== CartStatusEnum::OPEN) {
                throw new CartNotOpenException();
            }

            if ($quantity === 0) {
                $this->carts->removeItem($cartId, $productId);

                return;
            }

            $this->carts->saveItem($cartId, $productId, $quantity);
        }, IsolationLevelEnum::READ_COMMITTED);

        return $this->get($cartId);
    }

    /**
     * Locks the cart row for checkout and loads the cart whatever its status, so the caller
     * can recognize a repeated checkout before applying checkout rules. Callers must run
     * this inside a transaction.
     */
    public function lockForCheckout(CartId $id): Cart
    {
        $this->carts->lockStatus($id) ?? throw new CartNotFoundException();

        return $this->get($id);
    }

    public function markCheckedOut(CartId $id): void
    {
        $this->carts->updateStatus($id, CartStatusEnum::CHECKED_OUT);
    }
}
