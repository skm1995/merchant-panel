<?php

declare(strict_types=1);

namespace App\Shared\Logging;

use App\Shared\Config\Exception\InvalidEnvironmentVariableException;
use App\Shared\DependencyInjection\Env;
use App\Shared\DependencyInjection\ServiceFactory;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Writes one JSON object per line to stderr, where Docker collects it for Filebeat and ELK.
 */
final class LoggerFactory
{
    private const string CHANNEL = 'app';
    private const string STREAM = 'php://stderr';
    private const string LEVEL_VARIABLE = 'LOG_LEVEL';
    private const string LEVEL_EXPECTATION = 'a log level such as debug, info, or error';

    #[ServiceFactory]
    public static function create(CorrelationId $correlation, #[Env(self::LEVEL_VARIABLE)] string $level): LoggerInterface
    {
        $handler = new StreamHandler(self::STREAM, self::level($level));
        $handler->setFormatter(new JsonFormatter());

        return new Logger(self::CHANNEL, [$handler], [new CorrelationIdProcessor($correlation)]);
    }

    private static function level(string $name): Level
    {
        return array_find(Level::cases(), static fn (Level $level): bool => strcasecmp($level->getName(), $name) === 0)
            ?? throw new InvalidEnvironmentVariableException(self::LEVEL_VARIABLE, self::LEVEL_EXPECTATION);
    }
}
