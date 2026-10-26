<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Middleware\SignedInMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\SessionUserInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\HttpHarness;
use Tests\Unit\Support\RecordingHandler;

final class SignedInMiddlewareTest extends TestCase
{
    private const string PATH = '/api/v1/panel/orders';

    protected function tearDown(): void
    {
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testLetsSignedInUsersThrough(): void
    {
        $handler = new RecordingHandler();

        $this->process(true, $handler);

        self::assertCount(1, $handler->handled);
    }

    public function testAsksAnonymousVisitorsToSignIn(): void
    {
        $handler = new RecordingHandler();

        $output = $this->process(false, $handler);

        self::assertSame([], $handler->handled);
        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, http_response_code());
        self::assertStringContainsString('Sign in', $output);
    }

    private function process(bool $signedIn, RecordingHandler $handler): string
    {
        $user = $this->createStub(SessionUserInterface::class);
        $user->method('isSignedIn')->willReturn($signedIn);

        ob_start();
        new SignedInMiddleware($user, HttpHarness::responder())->process(new Request(HttpMethodEnum::GET->value, self::PATH), $handler);

        return (string) ob_get_clean();
    }
}
