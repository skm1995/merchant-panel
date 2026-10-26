<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\CsrfToken;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;

final class CsrfTokenTest extends TestCase
{
    private const string SESSION_KEY = '_csrf_token';
    private const string TOKEN = 'known-test-token';

    /** @var array<string, mixed> */
    private array $originalSession;
    private CsrfToken $csrfToken;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $this->csrfToken = new CsrfToken(new Session());
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    public function testCreatesAndStoresACryptographicallyRandomToken(): void
    {
        $token = $this->csrfToken->create();

        self::assertSame(64, strlen($token));
        self::assertSame($token, $_SESSION[self::SESSION_KEY]);
        self::assertNotSame($token, $this->csrfToken->create());
    }

    public function testReadsOnlyATokenThatWasCreated(): void
    {
        self::assertNull($this->csrfToken->get());
        self::assertArrayNotHasKey(self::SESSION_KEY, $_SESSION);

        $token = $this->csrfToken->create();

        self::assertSame($token, $this->csrfToken->get());
    }

    public function testValidatesOnlyMatchingStringToken(): void
    {
        $_SESSION[self::SESSION_KEY] = self::TOKEN;

        self::assertTrue($this->csrfToken->isValid(self::TOKEN));
        self::assertFalse($this->csrfToken->isValid('incorrect-token'));
        self::assertFalse($this->csrfToken->isValid(null));
    }

    public function testRejectsTokenWithoutGeneratingOneWhenSessionHasNone(): void
    {
        self::assertFalse($this->csrfToken->isValid(self::TOKEN));
        self::assertArrayNotHasKey(self::SESSION_KEY, $_SESSION);
    }
}
