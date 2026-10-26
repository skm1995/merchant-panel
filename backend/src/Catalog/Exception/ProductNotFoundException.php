<?php

declare(strict_types=1);

namespace App\Catalog\Exception;

use DomainException;

final class ProductNotFoundException extends DomainException
{
    private const string MESSAGE_FORMAT = 'Product %d does not exist.';

    public function __construct(int $productId)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $productId));
    }
}
