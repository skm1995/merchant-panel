<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Exception\PreconditionRequiredException;
use App\Shared\Http\Middleware\ErrorMiddleware;
use App\Shared\Http\Middleware\RequestHandlerInterface;
use App\Shared\Http\Request;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\HttpHarness;
use Throwable;

final class ErrorMiddlewareTest extends TestCase
{
    private const string API_PATH = '/api/v1/products';
    private const string PAGE_PATH = '/login';
    private const string DETAIL = 'The limit must be a positive integer.';

    protected function tearDown(): void
    {
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testAnswersAnInvalidApiRequestWithABadRequestProblem(): void
    {
        $output = $this->process(self::API_PATH, new InvalidRequestException(self::DETAIL));

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, http_response_code());
        self::assertStringContainsString(self::DETAIL, $output);
    }

    public function testAnswersAnInvalidBrowserRequestWithAPlainStatus(): void
    {
        $output = $this->process(self::PAGE_PATH, new InvalidRequestException(self::DETAIL));

        self::assertSame('400 Bad Request', $output);
    }

    public function testAnswersAMissingPreconditionWith428(): void
    {
        $this->process(self::API_PATH, new PreconditionRequiredException(self::DETAIL));

        self::assertSame(ResponseStatusEnum::PRECONDITION_REQUIRED->value, http_response_code());
    }

    public function testLetsOtherExceptionsPropagate(): void
    {
        $this->expectException(LogicException::class);

        $this->process(self::API_PATH, new LogicException(self::DETAIL));
    }

    private function process(string $path, Throwable $failure): string
    {
        $failing = new readonly class ($failure) implements RequestHandlerInterface {
            public function __construct(private Throwable $failure)
            {
            }

            public function handle(Request $request): void
            {
                throw $this->failure;
            }
        };

        ob_start();

        try {
            new ErrorMiddleware(HttpHarness::responder())->process(new Request(HttpMethodEnum::GET->value, $path), $failing);
        } finally {
            $output = ob_get_clean();
        }

        return (string) $output;
    }
}
