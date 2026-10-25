<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Cart\Entity\CartId;
use App\Order\Entity\OrderId;
use App\Order\Entity\OrderPlacement;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;

/** @implements EntityRecord<OrderPlacement> */
#[HydratedRecord]
final readonly class OrderPlacementRecord implements EntityRecord
{
    public string $publicId;
    public string $cartId;

    public function toEntity(): OrderPlacement
    {
        return new OrderPlacement(OrderId::fromBinary($this->publicId), CartId::fromBinary($this->cartId));
    }
}
