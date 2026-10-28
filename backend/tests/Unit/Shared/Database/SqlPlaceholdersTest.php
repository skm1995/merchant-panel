<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Database;

use App\Shared\Database\SqlPlaceholders;
use PHPUnit\Framework\TestCase;

final class SqlPlaceholdersTest extends TestCase
{
    public function testListsOnePositionalPlaceholderPerValue(): void
    {
        self::assertSame('?', SqlPlaceholders::positional(1));
        self::assertSame('?, ?, ?', SqlPlaceholders::positional(3));
    }

    public function testListsOneParenthesizedGroupPerRowForAMultiRowInsert(): void
    {
        self::assertSame('(?, ?)', SqlPlaceholders::rows(1, 2));
        self::assertSame('(?, ?, ?), (?, ?, ?)', SqlPlaceholders::rows(2, 3));
    }
}
