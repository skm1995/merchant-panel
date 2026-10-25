<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Shared\Database\HydratedRecord;

#[HydratedRecord]
final readonly class OrderViewRecord
{
    public int $internalId;
    public string $publicId;
    public string $status;
    public int $totalAmount;
    /** @var non-empty-string */
    public string $totalCurrency;
    public string $reservationExpiresAt;
    public string $createdAt;
}
