<?php

declare(strict_types=1);

namespace App\Analytics\ValueObject;

use DateTimeImmutable;

final readonly class SalesDay
{
    public function __construct(public DateTimeImmutable $date, public int $orders)
    {
    }
}
