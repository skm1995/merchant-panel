<?php

declare(strict_types=1);

namespace App\Shared\Database;

use PDO;
use PDOStatement;

/**
 * Hydrates query results into readonly record DTOs whose property names match the
 * selected column aliases, so repositories never pass associative row arrays around.
 */
final class RecordFetcher
{
    /**
     * @template T of object
     * @param class-string<T> $recordClass
     * @return list<T>
     */
    public static function all(PDOStatement $statement, string $recordClass): array
    {
        /** @var list<T> $records */
        $records = $statement->fetchAll(PDO::FETCH_CLASS, $recordClass);

        return $records;
    }

    /**
     * @template T of object
     * @param class-string<T> $recordClass
     * @return T|null
     */
    public static function one(PDOStatement $statement, string $recordClass): ?object
    {
        $statement->setFetchMode(PDO::FETCH_CLASS, $recordClass);
        $record = $statement->fetch();

        return $record instanceof $recordClass ? $record : null;
    }
}
