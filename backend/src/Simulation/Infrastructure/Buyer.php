<?php

declare(strict_types=1);

namespace App\Simulation\Infrastructure;

/**
 * One simulated buyer's progress through an order: which step their pending request belongs
 * to and what earlier steps returned.
 */
final class Buyer
{
    public BuyerStepEnum $step = BuyerStepEnum::CREATE_CART;
    public string $cartId = '';
    public string $orderId = '';
    public string $orderToken = '';
    public int $orderTotal = 0;
    public string $orderNumber = '';

    public function __construct(public readonly int $number)
    {
    }

    public function startOrder(string $orderNumber): void
    {
        $this->step = BuyerStepEnum::CREATE_CART;
        $this->orderNumber = $orderNumber;
    }
}
