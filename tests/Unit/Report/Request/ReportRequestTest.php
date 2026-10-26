<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Request;

use App\Report\Request\DailyRevenueRequest;
use App\Report\Request\TopProductsRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class ReportRequestTest extends TestCase
{
    private const string PATH = '/api/v1/reports/daily-revenue';

    public function testBuildsDailyRevenueQueryWithDefaultCurrency(): void
    {
        $query = DailyRevenueRequest::toQuery(self::request(['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO]));

        self::assertSame(ReportFixtures::FROM, $query->period->from->format('Y-m-d'));
        self::assertSame(ReportFixtures::CURRENCY, $query->period->currency->getCode());
    }

    public function testBuildsTopProductsQueryWithLimit(): void
    {
        $query = TopProductsRequest::toQuery(self::request([
            'from' => ReportFixtures::FROM,
            'to' => ReportFixtures::TO,
            'limit' => '3',
            'currency' => 'EUR',
        ]));

        self::assertSame(3, $query->limit);
        self::assertSame('EUR', $query->period->currency->getCode());
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidParameters(): iterable
    {
        yield 'missing dates' => [[]];
        yield 'not a date' => [['from' => 'yesterday', 'to' => ReportFixtures::TO]];
        yield 'impossible date' => [['from' => '2026-02-30', 'to' => ReportFixtures::TO]];
        yield 'lowercase currency' => [['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO, 'currency' => 'pln']];
        yield 'limit too large' => [['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO, 'limit' => '51']];
    }

    /** @param array<string, string> $parameters */
    #[DataProvider('invalidParameters')]
    public function testRejectsInvalidParameters(array $parameters): void
    {
        $this->expectException(InvalidRequestException::class);

        TopProductsRequest::toQuery(self::request($parameters));
    }

    /** @param array<string, string> $query */
    private static function request(array $query): Request
    {
        return new Request(HttpMethodEnum::GET->value, self::PATH, $query);
    }
}
