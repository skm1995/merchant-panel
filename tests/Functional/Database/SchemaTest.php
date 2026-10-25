<?php

declare(strict_types=1);

namespace Tests\Functional\Database;

use App\Shared\Database\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    private const string NULLABLE_COLUMNS_QUERY = <<<'SQL'
        SELECT CONCAT(table_name, '.', column_name)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND is_nullable = 'YES' AND table_name <> 'phinxlog'
        ORDER BY table_name, column_name
        SQL;
    private const array INTENTIONALLY_NULLABLE_COLUMNS = [
        'carts.updated_at',
        'orders.updated_at',
        'products.updated_at',
        'users.email_verified_at',
    ];

    public function testOnlyColumnsWithMeaningfulNullAreNullable(): void
    {
        $statement = DatabaseConnection::createFromEnvironment()->prepare(self::NULLABLE_COLUMNS_QUERY);
        $statement->execute();

        self::assertSame(self::INTENTIONALLY_NULLABLE_COLUMNS, $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
