<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Dto;

use App\Catalog\Dto\ListProductsQuery;
use App\Catalog\Service\ProductCatalog;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListProductsQueryTest extends TestCase
{
    private const string PATH = '/api/v1/products';
    private const string LIMIT = 'limit';
    private const string CURSOR = 'cursor';

    public function testUsesDefaultPageSizeAndFirstPageWithoutParameters(): void
    {
        $query = ListProductsQuery::fromRequest(self::request([]));

        self::assertSame(ProductCatalog::DEFAULT_PAGE_SIZE, $query->limit);
        self::assertNull($query->cursor);
    }

    public function testParsesPositiveIntegers(): void
    {
        $query = ListProductsQuery::fromRequest(self::request([self::LIMIT => '5', self::CURSOR => '42']));

        self::assertSame(5, $query->limit);
        self::assertSame(42, $query->cursor);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidParameters(): iterable
    {
        yield 'text limit' => [[self::LIMIT => 'abc']];
        yield 'zero limit' => [[self::LIMIT => '0']];
        yield 'negative cursor' => [[self::CURSOR => '-1']];
        yield 'fractional cursor' => [[self::CURSOR => '1.5']];
    }

    /** @param array<string, string> $parameters */
    #[DataProvider('invalidParameters')]
    public function testRejectsParametersThatAreNotPositiveIntegers(array $parameters): void
    {
        $this->expectException(InvalidRequestException::class);

        ListProductsQuery::fromRequest(self::request($parameters));
    }

    /** @param array<string, string> $query */
    private static function request(array $query): Request
    {
        return new Request(HttpMethodEnum::GET->value, self::PATH, $query);
    }
}
