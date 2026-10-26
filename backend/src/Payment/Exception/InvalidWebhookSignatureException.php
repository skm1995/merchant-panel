<?php

declare(strict_types=1);

namespace App\Payment\Exception;

use DomainException;

final class InvalidWebhookSignatureException extends DomainException
{
    private const string MESSAGE = 'The webhook signature is missing, invalid, or expired.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
