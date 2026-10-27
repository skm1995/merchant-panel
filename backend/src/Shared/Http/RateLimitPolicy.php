<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Config\Environment;
use App\Shared\Config\Exception\InvalidEnvironmentVariableException;

final readonly class RateLimitPolicy
{
    private const string POLICY_PATTERN = '#^([1-9]\d*)/([1-9]\d*)$#';
    private const string EXPECTATION = 'requests per seconds, such as 10/300';

    public function __construct(public int $limit, public int $windowSeconds)
    {
    }

    public static function fromEnvironment(string $key): self
    {
        if (preg_match(self::POLICY_PATTERN, Environment::get($key), $matches) !== 1) {
            throw new InvalidEnvironmentVariableException($key, self::EXPECTATION);
        }

        return new self((int) $matches[1], (int) $matches[2]);
    }
}
