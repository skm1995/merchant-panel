<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Repository\PdoUserRepository;
use App\Shared\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestUserData;

final class PdoUserRepositoryTest extends TestCase
{
    private const string EMAIL_PREFIX = 'user-repository-';
    private const string PASSWORD_HASH = 'password-hash';
    private const string MISSING_EMAIL_PREFIX = 'missing-user-';

    private PDO $pdo;
    private PdoUserRepository $users;
    private string $email;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->users = new PdoUserRepository($this->pdo);
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
    }

    protected function tearDown(): void
    {
        TestUserData::delete($this->pdo, $this->email);
    }

    public function testFindsCreatedUserAsUnverifiedEntity(): void
    {
        $this->users->create($this->email, self::PASSWORD_HASH);

        $user = $this->users->findByEmail($this->email);

        self::assertNotNull($user);
        self::assertSame($this->email, $user->email);
        self::assertSame(self::PASSWORD_HASH, $user->passwordHash);
        self::assertFalse($user->isVerified());
    }

    public function testReturnsNullForUnknownEmail(): void
    {
        self::assertNull($this->users->findByEmail(TestUserData::uniqueEmail(self::MISSING_EMAIL_PREFIX)));
    }

    public function testUniqueIndexViolationIsReportedAsDuplicateEmail(): void
    {
        $this->users->create($this->email, self::PASSWORD_HASH);

        $this->expectException(DuplicateEmailException::class);
        $this->users->create($this->email, self::PASSWORD_HASH);
    }
}
