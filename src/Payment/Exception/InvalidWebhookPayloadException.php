<?php

declare(strict_types=1);

namespace App\Payment\Exception;

use DomainException;

final class InvalidWebhookPayloadException extends DomainException
{
    private const string MESSAGE = 'The webhook payload does not match the provider format.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
