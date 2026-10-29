<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Middleware\AccessLogMiddleware;
use App\Shared\Http\Request;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\RecordingHandler;
use Tests\Unit\Support\RecordingResponseWriter;

final class AccessLogMiddlewareTest extends TestCase
{
    private const string PATH = '/api/v1/products';

    public function testLogsEveryRequestWithItsStatusAndDuration(): void
    {
        $log = new TestHandler();
        $response = new RecordingResponseWriter();
        $response->writeStatus(ResponseStatusEnum::CREATED);

        new AccessLogMiddleware(new Logger('test', [$log]), $response)
            ->process(new Request(HttpMethodEnum::POST->value, self::PATH), new RecordingHandler());

        $record = $log->getRecords()[0] ?? null;
        self::assertNotNull($record);
        self::assertSame(Level::Info, $record->level);
        self::assertSame('POST', $record->context['method']);
        self::assertSame(self::PATH, $record->context['path']);
        self::assertSame(201, $record->context['status']);
        self::assertIsInt($record->context['duration_ms']);
    }
}
