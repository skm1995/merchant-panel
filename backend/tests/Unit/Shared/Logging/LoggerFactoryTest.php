<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Logging;

use App\Shared\Config\Exception\InvalidEnvironmentVariableException;
use App\Shared\Logging\CorrelationId;
use App\Shared\Logging\LoggerFactory;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerFactoryTest extends TestCase
{
    public function testLogsAtTheConfiguredLevel(): void
    {
        $logger = LoggerFactory::create(new CorrelationId(), 'warning');

        self::assertInstanceOf(Logger::class, $logger);
        self::assertFalse($logger->isHandling(Level::Info));
        self::assertTrue($logger->isHandling(Level::Warning));
    }

    public function testRejectsAnUnknownLevel(): void
    {
        $this->expectException(InvalidEnvironmentVariableException::class);

        LoggerFactory::create(new CorrelationId(), 'chatty');
    }
}
