<?php

declare(strict_types=1);

namespace App\Payment\Entity;

use App\Shared\Identifier\RandomUuid;

final readonly class PaymentId extends RandomUuid
{
}
