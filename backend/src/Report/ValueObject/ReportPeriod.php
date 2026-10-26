<?php

declare(strict_types=1);

namespace App\Report\ValueObject;

use App\Report\Exception\InvalidReportPeriodException;
use DateTimeImmutable;
use Money\Currency;

/**
 * Whole days from $from to $to inclusive, queried as the half-open range [start, end) so
 * the created_at index can be used without wrapping the column in a function.
 */
final readonly class ReportPeriod
{
    public const int MAXIMUM_DAYS = 366;
    private const string START_OF_DAY = 'today';
    private const string NEXT_DAY = 'tomorrow';

    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public Currency $currency,
    ) {
        $days = (int) $this->startsAt()->diff($this->endsBefore())->format('%r%a');

        if ($days < 1 || $days > self::MAXIMUM_DAYS) {
            throw new InvalidReportPeriodException(self::MAXIMUM_DAYS);
        }
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->from->modify(self::START_OF_DAY);
    }

    public function endsBefore(): DateTimeImmutable
    {
        return $this->to->modify(self::NEXT_DAY);
    }
}
