<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Logging;

use App\Shared\Logging\CorrelationId;
use App\Shared\Logging\CorrelationIdProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class CorrelationIdProcessorTest extends TestCase
{
    private const string ID = 'request-1234';

    public function testAddsTheCurrentIdToEveryRecord(): void
    {
        $correlation = new CorrelationId();
        $correlation->set(self::ID);

        $record = new CorrelationIdProcessor($correlation)(self::record());

        self::assertSame(self::ID, $record->extra['request_id']);
    }

    public function testLeavesRecordsOutsideARequestUntouched(): void
    {
        $record = new CorrelationIdProcessor(new CorrelationId())(self::record());

        self::assertArrayNotHasKey('request_id', $record->extra);
    }

    private static function record(): LogRecord
    {
        return new LogRecord(new DateTimeImmutable(), 'app', Level::Info, 'message');
    }
}
