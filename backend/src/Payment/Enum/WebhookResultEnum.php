<?php

declare(strict_types=1);

namespace App\Payment\Enum;

enum WebhookResultEnum: string
{
    case PROCESSED = 'processed';
    case DUPLICATE = 'duplicate';
}
