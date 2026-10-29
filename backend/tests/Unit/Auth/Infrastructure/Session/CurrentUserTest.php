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
    private const string NEW_PASSWORD_HASH = 'new-password-hash';
    private const string EMAIL_SESSION_KEY = 'user_email';

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
        $this->storedUser = self::user(self::PASSWORD_HASH);
        $this->currentUser->signIn($this->storedUser);

        self::assertSame(self::EMAIL, $this->currentUser->email());

        $this->currentUser->signOut();

        self::assertNull($this->currentUser->email());
    }

    public function testKnowsWhetherSomeoneIsSignedIn(): void
    {
        self::assertFalse($this->currentUser->isSignedIn());

        $this->storedUser = self::user(self::PASSWORD_HASH);
        $this->currentUser->signIn($this->storedUser);

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

    public function testEndsTheSessionWhenThePasswordChanges(): void
    {
        $now = new DateTimeImmutable();
        $this->storedUser = new User(self::EMAIL, self::PASSWORD_HASH, $now, $now, $now);
        $this->currentUser->signIn($this->storedUser);

        $this->storedUser = new User(self::EMAIL, self::NEW_PASSWORD_HASH, $now, $now, $now);

        self::assertFalse($this->currentUser->isSignedIn());
        self::assertNull($this->currentUser->email());
        self::assertFalse($this->currentUser->isMerchant());
    }

    public function testEndsTheSessionWhenTheAccountIsGone(): void
    {
        $this->storedUser = self::user(self::PASSWORD_HASH);
        $this->currentUser->signIn($this->storedUser);

        $this->storedUser = null;

        self::assertFalse($this->currentUser->isSignedIn());
    }

    public function testDoesNotTrustASessionWithoutAPasswordStamp(): void
    {
        $this->storedUser = self::user(self::PASSWORD_HASH);
        $_SESSION = [self::EMAIL_SESSION_KEY => self::EMAIL];

        self::assertFalse($this->currentUser->isSignedIn());
    }

    private static function user(string $passwordHash): User
    {
        return new User(self::EMAIL, $passwordHash, new DateTimeImmutable());
    }
}
