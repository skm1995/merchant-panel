<?php

declare(strict_types=1);

namespace App\Report\Request;

use App\Report\Exception\InvalidReportPeriodException;
use App\Report\Query\ReportDate;
use App\Report\Query\ReportPeriod;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use DateTimeImmutable;
use Money\Currency;

/**
 * Parses the from, to, and currency query parameters shared by every report.
 */
final class ReportPeriodInput
{
    private const string FROM_PARAMETER = 'from';
    private const string TO_PARAMETER = 'to';
    private const string CURRENCY_PARAMETER = 'currency';
    private const string DEFAULT_CURRENCY = 'PLN';
    private const string CURRENCY_PATTERN = '/^[A-Z]{3}$/';
    private const string INVALID_DATE_FORMAT = 'The "%s" query parameter must be a date in YYYY-MM-DD format.';
    private const string INVALID_CURRENCY_MESSAGE = 'The "currency" query parameter must be an ISO 4217 code.';

    public static function fromRequest(Request $request): ReportPeriod
    {
        $currency = $request->query(self::CURRENCY_PARAMETER) ?? self::DEFAULT_CURRENCY;

        if (preg_match(self::CURRENCY_PATTERN, $currency) !== 1) {
            throw new InvalidRequestException(self::INVALID_CURRENCY_MESSAGE);
        }

        try {
            return new ReportPeriod(
                self::date($request, self::FROM_PARAMETER),
                self::date($request, self::TO_PARAMETER),
                new Currency($currency),
            );
        } catch (InvalidReportPeriodException $exception) {
            throw new InvalidRequestException($exception->getMessage(), previous: $exception);
        }
    }

    private static function date(Request $request, string $parameter): DateTimeImmutable
    {
        $value = $request->query($parameter);

        return ($value === null ? null : ReportDate::parse($value))
            ?? throw new InvalidRequestException(sprintf(self::INVALID_DATE_FORMAT, $parameter));
    }
}
