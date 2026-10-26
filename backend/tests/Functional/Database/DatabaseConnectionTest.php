<?php

declare(strict_types=1);

namespace Tests\Functional\Database;

use App\Shared\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    private const string LIMITED_QUERY = 'SELECT 1 AS value LIMIT :limit';
    private const string LIMIT_PARAMETER = 'limit';
    private const string VALUE_COLUMN = 'value';
    private const int LIMIT = 1;

    public function testUsesNativePreparedStatementsWithNativeTypes(): void
    {
        $pdo = DatabaseConnection::createFromEnvironment();
        $statement = $pdo->prepare(self::LIMITED_QUERY);
        $statement->execute([self::LIMIT_PARAMETER => self::LIMIT]);

        self::assertFalse($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        self::assertSame([[self::VALUE_COLUMN => 1]], $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
