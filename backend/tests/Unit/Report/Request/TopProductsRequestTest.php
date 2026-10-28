<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Request;

use App\Report\Request\ReportLimitInput;
use App\Report\Request\TopProductsRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class TopProductsRequestTest extends TestCase
{
    private const string PATH = '/api/v1/reports/top-products';

    public function testParsesThePeriodAndLimit(): void
    {
        $request = TopProductsRequest::fromRequest(self::request(['limit' => '3', 'currency' => 'EUR']));

        self::assertSame(3, $request->limit);
        self::assertSame('EUR', $request->period->currency->getCode());
    }

    public function testDefaultsTheLimit(): void
    {
        self::assertSame(ReportLimitInput::DEFAULT_LIMIT, TopProductsRequest::fromRequest(self::request([]))->limit);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => ['0'];
        yield 'too large' => ['51'];
        yield 'not a number' => ['ten'];
    }

    #[DataProvider('invalidLimits')]
    public function testRejectsInvalidLimits(string $limit): void
    {
        $this->expectException(InvalidRequestException::class);
        TopProductsRequest::fromRequest(self::request(['limit' => $limit]));
    }

    /** @param array<string, string> $query */
    private static function request(array $query): Request
    {
        return new Request(
            HttpMethodEnum::GET->value,
            self::PATH,
            ['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO, ...$query],
        );
    }
}
