<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\MapException;
use App\Shared\Http\Middleware\ExceptionMappingMiddleware;
use App\Shared\Http\Middleware\RequestHandlerInterface;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use DomainException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\RecordingResponseWriter;
use Throwable;

final class ExceptionMappingMiddlewareTest extends TestCase
{
    private const string PATH = '/api/v1/products/1';
    private const string DETAIL = 'The product does not exist.';

    private RecordingResponseWriter $response;

    protected function setUp(): void
    {
        $this->response = new RecordingResponseWriter();
    }


    public function testAnswersADeclaredExceptionWithItsProblemStatus(): void
    {
        $this->middleware()->process(new Request(HttpMethodEnum::GET->value, self::PATH), self::failing(new DomainException(self::DETAIL)));

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $this->response->status);
        self::assertStringContainsString(self::DETAIL, $this->response->body);
    }

    public function testLetsUndeclaredExceptionsPropagate(): void
    {
        $this->expectException(LogicException::class);

        $this->middleware()->process(new Request(HttpMethodEnum::GET->value, self::PATH), self::failing(new LogicException(self::DETAIL)));
    }

    private function middleware(): ExceptionMappingMiddleware
    {
        return new ExceptionMappingMiddleware([new MapException(DomainException::class, ResponseStatusEnum::NOT_FOUND)], new Responder($this->response));
    }

    private static function failing(Throwable $failure): RequestHandlerInterface
    {
        return new readonly class ($failure) implements RequestHandlerInterface {
            public function __construct(private Throwable $failure)
            {
            }

            public function handle(Request $request): void
            {
                throw $this->failure;
            }
        };
    }
}
