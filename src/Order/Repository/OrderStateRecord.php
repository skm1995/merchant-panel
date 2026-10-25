<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Shared\Database\HydratedRecord;

#[HydratedRecord]
final readonly class OrderStateRecord
{
    public int $internalId;
    public string $publicId;
    public string $status;
    public int $version;
}
