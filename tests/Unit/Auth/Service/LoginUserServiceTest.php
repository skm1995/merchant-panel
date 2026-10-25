<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\CurrentUser;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\UserAuthService;
use App\Shared\Http\Session;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class LoginUserServiceTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';
    private const string WRONG_PASSWORD = 'incorrect-password';

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

    public function testSignsInAuthenticatedUser(): void
    {
        $this->service()->execute(self::EMAIL, self::PASSWORD);

        self::assertSame(self::EMAIL, $this->currentUser->email());
    }

    public function testDoesNotSignInWithInvalidCredentials(): void
    {
        try {
            $this->service()->execute(self::EMAIL, self::WRONG_PASSWORD);
            self::fail('Expected invalid credentials to be rejected.');
        } catch (DomainException) {
            self::assertNull($this->currentUser->email());
        }
    }

    private function service(): LoginUserService
    {
        $user = new User(
            self::EMAIL,
            password_hash(self::PASSWORD, PASSWORD_DEFAULT),
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn($user);

        return new LoginUserService(new UserAuthService($users), $this->currentUser);
    }
}
