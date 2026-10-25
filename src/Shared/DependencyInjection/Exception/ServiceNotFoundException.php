<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection\Exception;

use LogicException;
use Psr\Container\NotFoundExceptionInterface;

final class ServiceNotFoundException extends LogicException implements NotFoundExceptionInterface
{
    private const string MESSAGE_FORMAT = 'Service "%s" is neither a class nor a configured definition.';

    public function __construct(string $id)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $id));
    }
}
