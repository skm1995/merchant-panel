<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Service\CurrentUser;
use App\Shared\Http\Session;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CurrentUserTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD_HASH = 'password-hash';

    /** @var array<string, mixed> */
    private array $originalSession;
    private CurrentUser $currentUser;

    protected function setUp(): void
    {
        $session = new Session();
        $session->start();
        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $this->currentUser = new CurrentUser($session);
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    public function testHasNoEmailBeforeSignIn(): void
    {
        self::assertNull($this->currentUser->email());
    }

    public function testRemembersSignedInUserUntilSignOut(): void
    {
        $this->currentUser->signIn(new User(self::EMAIL, self::PASSWORD_HASH, new DateTimeImmutable()));

        self::assertSame(self::EMAIL, $this->currentUser->email());

        $this->currentUser->signOut();

        self::assertNull($this->currentUser->email());
    }
}
