<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Middleware\ApiKeyMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\RequireApiKey;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\HttpHarness;
use Tests\Unit\Support\RecordingHandler;

final class ApiKeyMiddlewareTest extends TestCase
{
    private const string VARIABLE = 'API_KEY_MIDDLEWARE_TEST_KEY';
    private const string KEY = 'middleware-test-key';
    private const string PATH = '/api/v1/reports/daily-revenue';
    private const string HEADER = 'x-api-key';

    protected function setUp(): void
    {
        putenv(self::VARIABLE . '=' . self::KEY);
    }

    protected function tearDown(): void
    {
        putenv(self::VARIABLE);
        http_response_code(ResponseStatusEnum::OK->value);
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
            self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, http_response_code());
        }
    }

    /** @param array<string, string> $headers */
    private function process(array $headers, RecordingHandler $handler): void
    {
        ob_start();
        new ApiKeyMiddleware(new RequireApiKey(self::VARIABLE), HttpHarness::responder())
            ->process(new Request(HttpMethodEnum::GET->value, self::PATH, headers: $headers), $handler);
        ob_end_clean();
    }
}
