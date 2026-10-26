<?php

declare(strict_types=1);

namespace App\Cart\Repository;

use App\Shared\Database\HydratedRecord;

#[HydratedRecord]
final readonly class CartRecord
{
    /** Binary UUID (16 bytes). */
    public string $id;
    public string $status;
}
