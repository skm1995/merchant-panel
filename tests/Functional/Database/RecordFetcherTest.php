<?php

declare(strict_types=1);

namespace Tests\Functional\Database;

use App\Shared\Database\DatabaseConnection;
use App\Shared\Database\RecordFetcher;
use PDO;
use PHPUnit\Framework\TestCase;

final class RecordFetcherTest extends TestCase
{
    private const string RECORDS_QUERY = 'SELECT 7 AS id, \'first\' AS name UNION ALL SELECT 8, \'second\'';
    private const string EMPTY_QUERY = 'SELECT 7 AS id, \'first\' AS name FROM DUAL WHERE FALSE';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
    }

    public function testHydratesEveryRowIntoTypedReadonlyRecord(): void
    {
        $records = RecordFetcher::all($this->query(self::RECORDS_QUERY), ExampleRecord::class);

        self::assertCount(2, $records);
        self::assertSame(7, $records[0]->id);
        self::assertSame('second', $records[1]->name);
    }

    public function testReturnsSingleRecordOrNull(): void
    {
        self::assertSame(7, RecordFetcher::one($this->query(self::RECORDS_QUERY), ExampleRecord::class)?->id);
        self::assertNull(RecordFetcher::one($this->query(self::EMPTY_QUERY), ExampleRecord::class));
    }

    private function query(string $sql): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute();

        return $statement;
    }
}
