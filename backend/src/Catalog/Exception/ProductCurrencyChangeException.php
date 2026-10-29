<?php

declare(strict_types=1);

namespace App\Catalog\Exception;

use DomainException;

final class ProductCurrencyChangeException extends DomainException
{
    private const string MESSAGE_FORMAT = 'The price must stay in %s, the currency of the product.';

    public function __construct(string $currency)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $currency));
    }
}
