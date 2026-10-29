<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Session\CurrentUser;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\UserAuthService;
use App\Shared\Http\Session;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\CheapPasswordHash;

final class LoginUserServiceTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';
    private const string WRONG_PASSWORD = 'incorrect-password';

    /** @var array<string, mixed> */
    private array $originalSession;
    private UserRepositoryInterface $users;
    private CurrentUser $currentUser;

    protected function setUp(): void
    {
        $session = new Session();
        $session->start();
        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $user = new User(
            self::EMAIL,
            CheapPasswordHash::of(self::PASSWORD),
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn($user);
        $this->users = $users;
        $this->currentUser = new CurrentUser($session, $this->users);
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
        return new LoginUserService(new UserAuthService($this->users), $this->currentUser);
    }
}
