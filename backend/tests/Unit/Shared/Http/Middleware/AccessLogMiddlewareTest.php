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

final class AccessLogMiddlewareTest extends TestCase
{
    private const string PATH = '/api/v1/products';

    protected function tearDown(): void
    {
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testLogsEveryRequestWithItsStatusAndDuration(): void
    {
        $log = new TestHandler();
        http_response_code(ResponseStatusEnum::CREATED->value);

        new AccessLogMiddleware(new Logger('test', [$log]))
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
