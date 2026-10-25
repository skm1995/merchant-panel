<?php

declare(strict_types=1);

namespace App\Order\Command;

use App\Cart\Service\CartService;
use App\Catalog\Entity\Product;
use App\Catalog\Service\StockReservation;
use App\Order\Entity\Order;
use App\Order\Entity\OrderId;
use App\Order\Entity\OrderLine;
use App\Order\Exception\IdempotencyKeyReusedException;
use App\Order\Repository\OrderRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use Psr\Clock\ClockInterface;

/**
 * Checks out a cart: reserves stock and places an order with prices frozen at checkout.
 *
 * Runs in one READ COMMITTED transaction. The cart row is locked first, which serializes
 * concurrent checkouts of the same cart, so a repeated request with the same
 * Idempotency-Key finds the committed order and receives it again instead of a conflict.
 */
final class PlaceOrderHandler
{
    public const int RESERVATION_SECONDS = 900;
    private const string RESERVATION_MODIFIER_FORMAT = '+%d seconds';

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CartService $carts,
        private readonly StockReservation $stock,
        private readonly TransactionRunnerInterface $transactions,
        private readonly ClockInterface $clock,
    ) {
    }

    public function handle(PlaceOrderCommand $command): OrderId
    {
        return $this->transactions->run(
            fn (): OrderId => $this->place($command),
            IsolationLevelEnum::READ_COMMITTED,
        );
    }

    private function place(PlaceOrderCommand $command): OrderId
    {
        $cart = $this->carts->lockForCheckout($command->cartId);
        $placement = $this->orders->findPlacement($command->idempotencyKey);

        if ($placement !== null) {
            return $placement->cartId->equals($command->cartId)
                ? $placement->orderId
                : throw new IdempotencyKeyReusedException();
        }

        $cart->assertReadyForCheckout();
        $quantities = $cart->quantities();
        $lines = array_map(
            static fn (Product $product): OrderLine => new OrderLine(
                $product->id,
                $product->sku,
                $product->name,
                $quantities[$product->id],
                $product->price,
            ),
            $this->stock->reserve($quantities),
        );
        $order = Order::place(
            OrderId::generate(),
            $cart->id,
            $command->idempotencyKey,
            $lines,
            $this->clock->now()->modify(sprintf(self::RESERVATION_MODIFIER_FORMAT, self::RESERVATION_SECONDS)),
        );
        $this->orders->add($order);
        $this->carts->markCheckedOut($cart->id);

        return $order->id;
    }
}
