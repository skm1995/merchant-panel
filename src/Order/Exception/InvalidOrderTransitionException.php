<?php

declare(strict_types=1);

namespace App\Order\Exception;

use App\Order\Enum\OrderStatusEnum;
use DomainException;

final class InvalidOrderTransitionException extends DomainException
{
    private const string MESSAGE_FORMAT = 'An order cannot change from %s to %s.';

    public function __construct(OrderStatusEnum $from, OrderStatusEnum $to)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $from->value, $to->value));
    }
}
