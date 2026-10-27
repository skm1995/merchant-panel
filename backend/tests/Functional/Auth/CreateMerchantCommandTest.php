<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Auth\Repository\PdoUserRepository;
use App\Shared\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestUserData;

final class CreateMerchantCommandTest extends TestCase
{
    private const string COMMAND_FORMAT = 'MERCHANT_PASSWORD=%s php bin/create-merchant.php %s 2>&1';
    private const string EMAIL_PREFIX = 'create-merchant-';
    private const string PASSWORD = 'create-merchant-password';
    private const string CREATED_MESSAGE = 'can now sign in to the merchant panel';
    private const string EXISTS_MESSAGE = 'An account with this email already exists.';
    private const int ALREADY_EXISTS_STATUS = 3;

    private PDO $pdo;
    private string $email;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->email = TestUserData::uniqueEmail(self::EMAIL_PREFIX);
    }

    protected function tearDown(): void
    {
        TestUserData::delete($this->pdo, $this->email);
    }

    public function testCreatesAMerchantWhoCanSignInAndRefusesToCreateItTwice(): void
    {
        [$created, $createdOutput] = $this->create();
        [$repeated, $repeatedOutput] = $this->create();
        $user = new PdoUserRepository($this->pdo)->findByEmail($this->email);

        self::assertSame(0, $created, $createdOutput);
        self::assertStringContainsString(self::CREATED_MESSAGE, $createdOutput);
        self::assertNotNull($user);
        self::assertTrue($user->isVerified());
        self::assertTrue($user->isMerchant());
        self::assertTrue($user->passwordMatches(self::PASSWORD));
        self::assertSame(self::ALREADY_EXISTS_STATUS, $repeated, 'Deployment treats this status as "already there".');
        self::assertStringContainsString(self::EXISTS_MESSAGE, $repeatedOutput);
    }

    /** @return array{int, string} */
    private function create(): array
    {
        exec(sprintf(self::COMMAND_FORMAT, escapeshellarg(self::PASSWORD), escapeshellarg($this->email)), $output, $status);

        return [$status, implode(PHP_EOL, $output)];
    }
}
