<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Session;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Session\CurrentUser;
use App\Auth\Repository\UserRepositoryInterface;
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
    private ?User $storedUser = null;

    protected function setUp(): void
    {
        $session = new Session();
        $session->start();
        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturnCallback(fn (): ?User => $this->storedUser);
        $this->currentUser = new CurrentUser($session, $users);
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

    public function testKnowsWhetherSomeoneIsSignedIn(): void
    {
        self::assertFalse($this->currentUser->isSignedIn());

        $this->currentUser->signIn(new User('merchant@example.com', 'hash', new DateTimeImmutable()));

        self::assertTrue($this->currentUser->isSignedIn());
    }

    public function testIsAMerchantOnlyWhileSignedInToAnAccountWithMerchantAccess(): void
    {
        $now = new DateTimeImmutable();
        self::assertFalse($this->currentUser->isMerchant());

        $this->storedUser = new User(self::EMAIL, self::PASSWORD_HASH, $now, $now);
        $this->currentUser->signIn($this->storedUser);
        self::assertFalse($this->currentUser->isMerchant());

        $this->storedUser = new User(self::EMAIL, self::PASSWORD_HASH, $now, $now, $now);
        self::assertTrue($this->currentUser->isMerchant(), 'Access granted after sign-in applies to the next request.');
    }
}
