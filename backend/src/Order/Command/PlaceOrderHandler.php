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
use App\Order\Service\OrderAccess;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use App\Shared\DependencyInjection\Env;
use App\Shared\Messaging\Outbox\OutboxInterface;
use Psr\Clock\ClockInterface;

/**
 * Checks out a cart: reserves stock, places an order with prices frozen at checkout, and
 * schedules the reservation expiry through the outbox in the same transaction.
 *
 * Runs in one READ COMMITTED transaction. The cart row is locked first, which serializes
 * concurrent checkouts of the same cart, so a repeated request with the same
 * Idempotency-Key finds the committed order and receives it again instead of a conflict.
 */
final readonly class PlaceOrderHandler
{
    private const string RESERVATION_MODIFIER_FORMAT = '+%d seconds';

    public function __construct(
        private OrderRepositoryInterface $orders,
        private CartService $carts,
        private StockReservation $stock,
        private OutboxInterface $outbox,
        private TransactionRunnerInterface $transactions,
        private ClockInterface $clock,
        private OrderAccess $access,
        #[Env('ORDER_RESERVATION_SECONDS')] private readonly int $reservationSeconds,
    ) {
    }

    public function handle(PlaceOrderCommand $command): PlaceOrderResult
    {
        $orderId = $this->transactions->run(
            fn (): OrderId => $this->place($command),
            IsolationLevelEnum::READ_COMMITTED,
        );

        return new PlaceOrderResult($orderId, $this->access->tokenFor($orderId));
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
            $this->clock->now()->modify(sprintf(self::RESERVATION_MODIFIER_FORMAT, $this->reservationSeconds)),
        );
        $this->orders->add($order);
        $this->carts->markCheckedOut($cart->id);
        $this->outbox->add(new ExpireOrderReservationCommand($order->id), $order->reservationExpiresAt);

        return $order->id;
    }
}
