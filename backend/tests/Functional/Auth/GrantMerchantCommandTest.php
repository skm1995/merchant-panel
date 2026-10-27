<?php

declare(strict_types=1);

namespace Tests\Functional\Auth;

use App\Auth\Repository\PdoUserRepository;
use App\Shared\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\TestUserData;

final class GrantMerchantCommandTest extends TestCase
{
    private const string COMMAND_FORMAT = 'php bin/grant-merchant.php %s 2>&1';
    private const string EMAIL_PREFIX = 'grant-merchant-';
    private const string UNKNOWN_EMAIL_PREFIX = 'grant-unknown-';
    private const string PASSWORD = 'grant-merchant-password';
    private const string GRANTED_MESSAGE = 'now has access to the merchant panel';
    private const string UNKNOWN_MESSAGE = 'No account uses this email address.';

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

    public function testGrantsMerchantAccessToAConfirmedAccount(): void
    {
        TestUserData::createVerified($this->pdo, $this->email, self::PASSWORD);

        [$status, $output] = $this->grant($this->email);

        self::assertSame(0, $status, $output);
        self::assertStringContainsString(self::GRANTED_MESSAGE, $output);
        self::assertTrue(new PdoUserRepository($this->pdo)->findByEmail($this->email)?->isMerchant() ?? false);
    }

    public function testFailsForAnUnknownEmail(): void
    {
        [$status, $output] = $this->grant(TestUserData::uniqueEmail(self::UNKNOWN_EMAIL_PREFIX));

        self::assertSame(1, $status);
        self::assertStringContainsString(self::UNKNOWN_MESSAGE, $output);
    }

    /** @return array{int, string} */
    private function grant(string $email): array
    {
        exec(sprintf(self::COMMAND_FORMAT, escapeshellarg($email)), $output, $status);

        return [$status, implode(PHP_EOL, $output)];
    }
}
