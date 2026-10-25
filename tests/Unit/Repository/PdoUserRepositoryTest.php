<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Entity\User;
use App\Repository\PdoUserRepository;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class PdoUserRepositoryTest extends TestCase
{
    private const string SELECT_QUERY = 'SELECT email, password, created_at, email_verified_at FROM users WHERE email = :email';
    private const string INSERT_QUERY = 'INSERT INTO users (email, password, created_at) VALUES (:email, :password, CURRENT_TIMESTAMP)';
    private const string EMAIL = 'person@example.com';
    private const string HASH = 'password-hash';
    private const string CREATED_AT = '2026-10-25 10:00:00';

    public function testFindByEmailReturnsUserWhenFound(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with(['email' => self::EMAIL]);
        $statement->expects(self::once())->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn([
            'email' => self::EMAIL,
            'password' => self::HASH,
            'created_at' => self::CREATED_AT,
            'email_verified_at' => null,
        ]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->with(self::SELECT_QUERY)->willReturn($statement);

        $user = new PdoUserRepository($pdo)->findByEmail(self::EMAIL);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(self::EMAIL, $user->email);
        self::assertSame(self::HASH, $user->password);
        self::assertSame(self::CREATED_AT, $user->createdAt->format('Y-m-d H:i:s'));
    }

    public function testFindByEmailReturnsNullWhenMissing(): void
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetch')->willReturn(false);
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($statement);

        self::assertNull(new PdoUserRepository($pdo)->findByEmail(self::EMAIL));
    }

    public function testCreateInsertsUserAndReturnsEntity(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with([
            'email' => self::EMAIL,
            'password' => self::HASH,
        ]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->with(self::INSERT_QUERY)->willReturn($statement);

        $user = new PdoUserRepository($pdo)->create(self::EMAIL, self::HASH);

        self::assertSame(self::EMAIL, $user->email);
        self::assertSame(self::HASH, $user->password);
        self::assertInstanceOf(DateTimeImmutable::class, $user->createdAt);
    }
}
