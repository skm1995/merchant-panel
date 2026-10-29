<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Middleware\ApiKeyMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\RequireApiKey;
use App\Shared\Http\Responder;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\RecordingHandler;
use Tests\Unit\Support\RecordingResponseWriter;

final class ApiKeyMiddlewareTest extends TestCase
{
    private const string VARIABLE = 'API_KEY_MIDDLEWARE_TEST_KEY';
    private const string KEY = 'middleware-test-key';
    private const string PATH = '/api/v1/reports/daily-revenue';
    private const string HEADER = 'x-api-key';

    private RecordingResponseWriter $response;

    protected function setUp(): void
    {
        putenv(self::VARIABLE . '=' . self::KEY);
        $this->response = new RecordingResponseWriter();
    }

    protected function tearDown(): void
    {
        putenv(self::VARIABLE);
    }

    public function testLetsRequestsWithTheConfiguredKeyThrough(): void
    {
        $handler = new RecordingHandler();

        $this->process([self::HEADER => self::KEY], $handler);

        self::assertCount(1, $handler->handled);
    }

    public function testRejectsMissingOrWrongKeys(): void
    {
        foreach ([[], [self::HEADER => 'wrong']] as $headers) {
            $handler = new RecordingHandler();

            $this->process($headers, $handler);

            self::assertSame([], $handler->handled);
            self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, $this->response->status);
        }
    }

    /** @param array<string, string> $headers */
    private function process(array $headers, RecordingHandler $handler): void
    {
        new ApiKeyMiddleware(new RequireApiKey(self::VARIABLE), new Responder($this->response))
            ->process(new Request(HttpMethodEnum::GET->value, self::PATH, headers: $headers), $handler);
    }
}
