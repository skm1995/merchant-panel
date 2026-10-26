<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Middleware\SessionMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\RecordingHandler;

final class SessionMiddlewareTest extends TestCase
{
    private const string PATH = '/login';

    public function testStartsTheSessionBeforeHandingTheRequestOn(): void
    {
        $handler = new RecordingHandler();

        new SessionMiddleware(new Session())->process(new Request(HttpMethodEnum::GET->value, self::PATH), $handler);

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertCount(1, $handler->handled);
    }
}
