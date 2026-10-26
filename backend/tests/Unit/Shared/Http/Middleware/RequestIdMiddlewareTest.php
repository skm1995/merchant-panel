<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Middleware\RequestIdMiddleware;
use App\Shared\Http\Request;
use App\Shared\Logging\CorrelationId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Tests\Unit\Support\RecordingHandler;

final class RequestIdMiddlewareTest extends TestCase
{
    private const string PATH = '/api/v1/products';
    private const string HEADER = 'x-request-id';
    private const string CLIENT_ID = 'checkout-7f3a9c21';

    public function testGivesARequestWithoutAnIdANewOne(): void
    {
        $correlation = $this->process([]);

        self::assertTrue(Uuid::isValid((string) $correlation->get()));
    }

    public function testKeepsAWellFormedIdFromTheClient(): void
    {
        self::assertSame(self::CLIENT_ID, $this->process([self::HEADER => self::CLIENT_ID])->get());
    }

    public function testReplacesAnIdThatCouldPolluteTheLogs(): void
    {
        $correlation = $this->process([self::HEADER => "forged\nlog line"]);

        self::assertTrue(Uuid::isValid((string) $correlation->get()));
    }

    /** @param array<string, string> $headers */
    private function process(array $headers): CorrelationId
    {
        $correlation = new CorrelationId();
        $handler = new RecordingHandler();

        new RequestIdMiddleware($correlation)->process(new Request(HttpMethodEnum::GET->value, self::PATH, headers: $headers), $handler);

        self::assertCount(1, $handler->handled);

        return $correlation;
    }
}
