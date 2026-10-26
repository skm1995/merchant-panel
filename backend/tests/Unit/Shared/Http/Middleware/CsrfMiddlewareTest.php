<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Middleware\CsrfMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\HttpHarness;
use Tests\Unit\Support\RecordingHandler;

final class CsrfMiddlewareTest extends TestCase
{
    private const string PAGE_PATH = '/register';
    private const string API_PATH = '/api/v1/session';
    private const string CSRF_HEADER = 'x-csrf-token';
    private const string WRONG_TOKEN = 'wrong-token';

    /** @var array<string, mixed> */
    private array $originalSession;
    private CsrfToken $csrfToken;
    private RecordingHandler $handler;

    protected function setUp(): void
    {
        $session = new Session();
        $session->start();
        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $this->csrfToken = new CsrfToken($session);
        $this->handler = new RecordingHandler();
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testLetsSafeRequestsThroughWithoutAToken(): void
    {
        $this->process(new Request(HttpMethodEnum::GET->value, self::PAGE_PATH));

        self::assertCount(1, $this->handler->handled);
    }

    public function testAcceptsTheTokenFromAFormField(): void
    {
        $token = $this->csrfToken->create();

        $this->process(new Request(HttpMethodEnum::POST->value, self::PAGE_PATH, post: [CsrfToken::FIELD_NAME => $token]));

        self::assertCount(1, $this->handler->handled);
    }

    public function testAcceptsTheTokenFromTheHeader(): void
    {
        $token = $this->csrfToken->create();

        $this->process(new Request(HttpMethodEnum::POST->value, self::API_PATH, headers: [self::CSRF_HEADER => $token]));

        self::assertCount(1, $this->handler->handled);
    }

    public function testRejectsStateChangesWithoutAValidToken(): void
    {
        $this->csrfToken->create();

        $output = $this->process(new Request(HttpMethodEnum::POST->value, self::API_PATH, headers: [self::CSRF_HEADER => self::WRONG_TOKEN]));

        self::assertSame([], $this->handler->handled);
        self::assertSame(ResponseStatusEnum::FORBIDDEN->value, http_response_code());
        self::assertStringContainsString('CSRF', $output);
    }

    private function process(Request $request): string
    {
        ob_start();
        new CsrfMiddleware($this->csrfToken, HttpHarness::responder())->process($request, $this->handler);

        return (string) ob_get_clean();
    }
}
