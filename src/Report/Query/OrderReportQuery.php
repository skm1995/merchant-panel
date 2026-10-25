<?php

declare(strict_types=1);

namespace App\Report\Query;

use App\Order\Enum\OrderStatusEnum;
use DateTimeImmutable;

final readonly class OrderReportQuery
{
    public const int LIMIT = 50;

    public function __construct(public OrderStatusEnum $status, public DateTimeImmutable $since)
    {
    }
}
