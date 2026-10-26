<?php

declare(strict_types=1);

namespace App\Order\Entity;

use App\Shared\Identifier\RandomUuid;

/**
 * Public order identifier exposed by the API; the internal integer key never leaves the database.
 */
final readonly class OrderId extends RandomUuid
{
}
