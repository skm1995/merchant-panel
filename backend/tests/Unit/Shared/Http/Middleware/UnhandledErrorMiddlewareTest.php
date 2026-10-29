<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Middleware\RequestHandlerInterface;
use App\Shared\Http\Middleware\UnhandledErrorMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Unit\Support\RecordingResponseWriter;

final class UnhandledErrorMiddlewareTest extends TestCase
{
    private const string PATH = '/api/v1/products';
    private const string SECRET_DETAIL = 'SQLSTATE[HY000]: connection to db.internal refused';

    private RecordingResponseWriter $response;

    protected function setUp(): void
    {
        $this->response = new RecordingResponseWriter();
    }


    public function testLogsTheFailureAndAnswersWithoutLeakingIt(): void
    {
        $log = new TestHandler();
        $failing = new readonly class (new RuntimeException(self::SECRET_DETAIL)) implements RequestHandlerInterface {
            public function __construct(private RuntimeException $failure)
            {
            }

            public function handle(Request $request): void
            {
                throw $this->failure;
            }
        };

        new UnhandledErrorMiddleware(new Logger('test', [$log]), new Responder($this->response))
            ->process(new Request(HttpMethodEnum::GET->value, self::PATH), $failing);

        self::assertSame(ResponseStatusEnum::INTERNAL_SERVER_ERROR->value, $this->response->status);
        self::assertStringNotContainsString(self::SECRET_DETAIL, $this->response->body);
        self::assertTrue($log->hasRecordThatPasses(
            static fn ($record): bool => $record->level === Level::Error && ($record->context['exception'] ?? null) instanceof RuntimeException,
            Level::Error,
        ));
    }
}
