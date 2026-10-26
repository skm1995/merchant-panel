<?php

declare(strict_types=1);

namespace App\Report\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Owns the YYYY-MM-DD date format that reports accept and return.
 */
final class ReportDate
{
    private const string FORMAT = 'Y-m-d';
    private const string PARSE_FORMAT = '!' . self::FORMAT;

    /**
     * Parses a real calendar date at midnight; impossible dates such as 2026-02-30 are rejected.
     */
    public static function parse(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(self::PARSE_FORMAT, $value);

        return $date !== false && self::format($date) === $value ? $date : null;
    }

    public static function format(DateTimeInterface $date): string
    {
        return $date->format(self::FORMAT);
    }
}
