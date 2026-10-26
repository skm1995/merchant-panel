<?php

declare(strict_types=1);

namespace Tests\Unit\Report\Request;

use App\Report\Request\ReportPeriodInput;
use App\Report\ValueObject\ReportDate;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Report\ReportFixtures;

final class ReportPeriodInputTest extends TestCase
{
    private const string PATH = '/api/v1/reports/daily-revenue';

    public function testParsesThePeriodWithTheDefaultCurrency(): void
    {
        $period = ReportPeriodInput::parse(self::request(['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO]));

        self::assertSame(ReportFixtures::FROM, ReportDate::format($period->from));
        self::assertSame(ReportFixtures::TO, ReportDate::format($period->to));
        self::assertSame(ReportFixtures::CURRENCY, $period->currency->getCode());
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidParameters(): iterable
    {
        yield 'missing dates' => [[]];
        yield 'not a date' => [['from' => 'yesterday', 'to' => ReportFixtures::TO]];
        yield 'impossible date' => [['from' => '2026-02-30', 'to' => ReportFixtures::TO]];
        yield 'lowercase currency' => [['from' => ReportFixtures::FROM, 'to' => ReportFixtures::TO, 'currency' => 'pln']];
        yield 'end before start' => [['from' => ReportFixtures::TO, 'to' => ReportFixtures::FROM]];
    }

    /** @param array<string, string> $parameters */
    #[DataProvider('invalidParameters')]
    public function testRejectsInvalidParameters(array $parameters): void
    {
        $this->expectException(InvalidRequestException::class);
        ReportPeriodInput::parse(self::request($parameters));
    }

    /** @param array<string, string> $query */
    private static function request(array $query): Request
    {
        return new Request(HttpMethodEnum::GET->value, self::PATH, $query);
    }
}
