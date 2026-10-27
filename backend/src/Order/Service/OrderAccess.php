<?php

declare(strict_types=1);

namespace App\Order\Service;

use App\Order\Entity\OrderId;
use App\Order\Exception\OrderNotFoundException;
use App\Shared\DependencyInjection\Env;

/**
 * Proves that a caller may see and act on a guest's order. Checkout hands out a token, an
 * HMAC of the order id, so the id alone, which the panel, logs, and URLs show, opens nothing;
 * a repeated checkout gets the same token again without it being stored.
 */
final readonly class OrderAccess
{
    private const string ALGORITHM = 'sha256';

    public function __construct(#[Env('ORDER_ACCESS_SECRET')] private string $secret)
    {
    }

    public function tokenFor(OrderId $orderId): string
    {
        return hash_hmac(self::ALGORITHM, $orderId->value, $this->secret);
    }

    /**
     * A wrong token is answered as an unknown order, so it does not reveal that the order exists.
     *
     * @throws OrderNotFoundException
     */
    public function verify(OrderId $orderId, string $token): void
    {
        if (!hash_equals($this->tokenFor($orderId), $token)) {
            throw new OrderNotFoundException();
        }
    }
}
