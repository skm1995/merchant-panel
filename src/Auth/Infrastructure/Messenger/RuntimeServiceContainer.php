<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messenger;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;

final class RuntimeServiceContainer implements ContainerInterface
{
    private const string UNKNOWN_SERVICE_MESSAGE = 'Unknown service identifier.';

    /** @param array<string, object> $services */
    public function __construct(private readonly array $services)
    {
    }

    public function get(string $id): mixed
    {
        return $this->services[$id] ?? throw new InvalidArgumentException(self::UNKNOWN_SERVICE_MESSAGE);
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
