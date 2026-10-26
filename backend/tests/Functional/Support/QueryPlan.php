<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use PDO;
use PHPUnit\Framework\Assert;

/**
 * Reads how MySQL accesses a table in a query from EXPLAIN FORMAT=JSON.
 */
final class QueryPlan
{
    private const string EXPLAIN_FORMAT = 'EXPLAIN FORMAT=JSON %s';
    private const string TABLE_NAME_KEY = 'table_name';

    /**
     * @param array<string, int|string> $parameters
     * @return array<string, mixed> the plan node of the table, with keys such as key and access_type
     */
    public static function tableAccess(PDO $pdo, string $query, array $parameters, string $table): array
    {
        $statement = $pdo->prepare(sprintf(self::EXPLAIN_FORMAT, $query));

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->execute();
        $plan = json_decode((string) $statement->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);
        Assert::assertIsArray($plan);
        $node = self::findTable($plan, $table);
        Assert::assertNotNull($node, sprintf('The plan does not read the %s table.', $table));

        return $node;
    }

    /**
     * @param array<mixed> $plan
     * @return array<string, mixed>|null
     */
    private static function findTable(array $plan, string $table): ?array
    {
        if (($plan[self::TABLE_NAME_KEY] ?? null) === $table) {
            /** @var array<string, mixed> $plan */
            return $plan;
        }

        foreach ($plan as $child) {
            $found = is_array($child) ? self::findTable($child, $table) : null;

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
