<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use DomainException;

/**
 * An expected authentication failure whose message can be shown to the user.
 */
abstract class AuthException extends DomainException
{
}
