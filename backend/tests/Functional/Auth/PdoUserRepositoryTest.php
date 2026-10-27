<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Repository\PdoUserRepository;
use App\Shared\Database\DatabaseConnection;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestUserData;

final class PdoUserRepositoryTest extends TestCase
{
    private const string EMAIL_PREFIX = 'user-repository-';
    private const string PASSWORD_HASH = 'password-hash';
    private const string MISSING_EMAIL_PREFIX = 'missing-user-';
    private const string BACKDATE_QUERY = "UPDATE users SET created_at = '2026-01-01 00:00:00' WHERE email = :email";
    private const string CUTOFF = '2026-02-01 00:00:00';
    private const string VERIFY_QUERY = 'UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE email = :email';

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

    public function testReturnsTheUserAsTheDatabaseStoredIt(): void
    {
        $created = $this->users->create($this->email, self::PASSWORD_HASH);

        self::assertEquals($this->users->findByEmail($this->email), $created);
    }

    public function testGrantsMerchantAccessOnlyToAConfirmedUser(): void
    {
        $this->users->create($this->email, self::PASSWORD_HASH);
        $this->users->grantMerchantAccess($this->email, new DateTimeImmutable());
        self::assertFalse($this->users->findByEmail($this->email)?->isMerchant() ?? true, 'An unconfirmed address gets no access.');

        $this->pdo->prepare(self::VERIFY_QUERY)->execute(['email' => $this->email]);
        $this->users->grantMerchantAccess($this->email, new DateTimeImmutable());

        self::assertTrue($this->users->findByEmail($this->email)?->isMerchant() ?? false);
    }

    public function testDeletesOnlyAccountsLeftUnconfirmedSinceBeforeTheCutoff(): void
    {
        $recent = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
        $confirmed = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
        $this->users->create($this->email, self::PASSWORD_HASH);
        $this->users->create($recent, self::PASSWORD_HASH);
        TestUserData::createVerified($this->pdo, $confirmed, self::PASSWORD_HASH);
        $this->pdo->prepare(self::BACKDATE_QUERY)->execute(['email' => $this->email]);
        $this->pdo->prepare(self::BACKDATE_QUERY)->execute(['email' => $confirmed]);

        try {
            $this->users->deleteUnverifiedCreatedBefore(new DateTimeImmutable(self::CUTOFF));

            self::assertNull($this->users->findByEmail($this->email));
            self::assertNotNull($this->users->findByEmail($recent));
            self::assertNotNull($this->users->findByEmail($confirmed));
        } finally {
            TestUserData::delete($this->pdo, $recent);
            TestUserData::delete($this->pdo, $confirmed);
        }
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
