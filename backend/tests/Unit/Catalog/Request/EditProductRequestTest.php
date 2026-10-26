<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Request;

use App\Catalog\Request\EditProductRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Exception\PreconditionRequiredException;
use App\Shared\Http\Request;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditProductRequestTest extends TestCase
{
    private const string PATH = '/api/v1/panel/products/5';
    private const string VALID_BODY = '{"price":{"amount":"2500","currency":"PLN"},"stock":12}';
    private const string IF_MATCH = 'if-match';

    public function testReadsTheProductPriceStockAndExpectedVersion(): void
    {
        $edit = EditProductRequest::fromRequest(self::request(self::VALID_BODY, [self::IF_MATCH => '"3"']));

        self::assertSame(5, $edit->productId);
        self::assertTrue(Money::PLN(2500)->equals($edit->price));
        self::assertSame(12, $edit->stock);
        self::assertSame(3, $edit->expectedVersion);
    }

    public function testRequiresTheVersionTheClientEdited(): void
    {
        $this->expectException(PreconditionRequiredException::class);

        EditProductRequest::fromRequest(self::request(self::VALID_BODY, []));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidBodies(): iterable
    {
        yield 'amount with a decimal point' => ['{"price":{"amount":"25.00","currency":"PLN"},"stock":12}'];
        yield 'negative amount' => ['{"price":{"amount":"-1","currency":"PLN"},"stock":12}'];
        yield 'unknown currency format' => ['{"price":{"amount":"2500","currency":"zł"},"stock":12}'];
        yield 'negative stock' => ['{"price":{"amount":"2500","currency":"PLN"},"stock":-1}'];
        yield 'stock as text' => ['{"price":{"amount":"2500","currency":"PLN"},"stock":"12"}'];
        yield 'missing price' => ['{"stock":12}'];
    }

    #[DataProvider('invalidBodies')]
    public function testRejectsInvalidBodies(string $body): void
    {
        $this->expectException(InvalidRequestException::class);

        EditProductRequest::fromRequest(self::request($body, [self::IF_MATCH => '"3"']));
    }

    /** @param array<string, string> $headers */
    private static function request(string $body, array $headers): Request
    {
        return new Request(HttpMethodEnum::PATCH->value, self::PATH, headers: $headers, body: $body)
            ->withRouteParameters(['id' => '5']);
    }
}
