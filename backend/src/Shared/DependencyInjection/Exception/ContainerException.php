<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection\Exception;

use LogicException;
use Psr\Container\ContainerExceptionInterface;

final class ContainerException extends LogicException implements ContainerExceptionInterface
{
}
