<?php

declare(strict_types=1);

namespace App\Catalog\Exception;

use DomainException;

final class InsufficientStockException extends DomainException
{
    private const string MESSAGE_FORMAT = 'Product %d does not have enough stock available.';

    public function __construct(public readonly int $productId)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $productId));
    }
}
