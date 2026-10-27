<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Response;

use App\Auth\Response\SessionResponse;
use PHPUnit\Framework\TestCase;

final class SessionResponseTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string TOKEN = 'csrf-token';

    public function testDescribesASignedInSession(): void
    {
        self::assertSame(
            ['authenticated' => true, 'email' => self::EMAIL, 'merchant' => true, 'csrf_token' => self::TOKEN],
            new SessionResponse(self::EMAIL, true, self::TOKEN)->toArray(),
        );
    }

    public function testDescribesAnAnonymousSession(): void
    {
        self::assertSame(
            ['authenticated' => false, 'email' => null, 'merchant' => false, 'csrf_token' => self::TOKEN],
            new SessionResponse(null, false, self::TOKEN)->toArray(),
        );
    }
}
