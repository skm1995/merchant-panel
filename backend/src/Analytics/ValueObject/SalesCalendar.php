<?php

declare(strict_types=1);

namespace App\Analytics\ValueObject;

use DateTimeImmutable;

/**
 * Spreads a number of historical orders over the days before today the way a growing shop
 * sells: more every year, more in the evening of the year, and with peaks on Black Friday,
 * Cyber Monday, and before Christmas. The result is deterministic for a given day.
 */
final readonly class SalesCalendar
{
    private const string DAY_MODIFIER_FORMAT = '-%d days';
    private const string NEXT_DAY = '+1 day';
    private const string BLACK_FRIDAY_FORMAT = 'fourth friday of november %d';
    private const string CYBER_MONDAY_OFFSET = '+3 days';
    private const string MONTH_FORMAT = 'n';
    private const string DAY_OF_MONTH_FORMAT = 'j';
    private const string WEEKDAY_FORMAT = 'N';
    private const string YEAR_FORMAT = 'Y';
    private const string DATE_FORMAT = 'Y-m-d';
    private const float YEARLY_GROWTH = 0.35;
    private const float BLACK_FRIDAY = 6.0;
    private const float CYBER_MONDAY = 3.5;
    private const float BLACK_FRIDAY_WEEKEND = 2.5;
    private const float CHRISTMAS_SHOPPING = 1.6;
    private const int CHRISTMAS_SHOPPING_LAST_DAY = 20;
    private const int DECEMBER = 12;
    private const int SATURDAY = 6;
    private const int SUNDAY = 7;
    private const int DAYS_PER_YEAR = 365;

    /** @var array<int, float> */
    private const array MONTH_WEIGHTS = [
        1 => 0.8, 2 => 0.8, 3 => 0.9, 4 => 0.95, 5 => 1.0, 6 => 0.95,
        7 => 0.9, 8 => 0.9, 9 => 1.0, 10 => 1.05, 11 => 1.25, 12 => 1.2,
    ];

    /** @var array<int, float> */
    private const array WEEKDAY_WEIGHTS = [self::SATURDAY => 0.85, self::SUNDAY => 1.1];

    public function __construct(private DateTimeImmutable $today, private int $days)
    {
    }

    /**
     * @return list<SalesDay> one entry per day, oldest first, whose orders add up exactly
     */
    public function distribute(int $orders): array
    {
        $dates = [];
        $weights = [];
        $date = $this->today->setTime(0, 0)->modify(sprintf(self::DAY_MODIFIER_FORMAT, $this->days));

        for ($index = 0; $index < $this->days; $index++) {
            $dates[] = $date;
            $weights[] = $this->weight($date, $index);
            $date = $date->modify(self::NEXT_DAY);
        }

        return array_map(
            static fn (DateTimeImmutable $day, int $count): SalesDay => new SalesDay($day, $count),
            $dates,
            self::apportion($orders, $weights),
        );
    }

    private function weight(DateTimeImmutable $date, int $index): float
    {
        $year = (int) $date->format(self::YEAR_FORMAT);
        $blackFriday = new DateTimeImmutable(sprintf(self::BLACK_FRIDAY_FORMAT, $year));
        $day = $date->format(self::DATE_FORMAT);
        $month = (int) $date->format(self::MONTH_FORMAT);

        $peak = match (true) {
            $day === $blackFriday->format(self::DATE_FORMAT) => self::BLACK_FRIDAY,
            $day === $blackFriday->modify(self::CYBER_MONDAY_OFFSET)->format(self::DATE_FORMAT) => self::CYBER_MONDAY,
            $date > $blackFriday && $date < $blackFriday->modify(self::CYBER_MONDAY_OFFSET) => self::BLACK_FRIDAY_WEEKEND,
            $month === self::DECEMBER && (int) $date->format(self::DAY_OF_MONTH_FORMAT) <= self::CHRISTMAS_SHOPPING_LAST_DAY => self::CHRISTMAS_SHOPPING,
            default => 1.0,
        };

        return (1 + self::YEARLY_GROWTH * $index / self::DAYS_PER_YEAR)
            * self::MONTH_WEIGHTS[$month]
            * (self::WEEKDAY_WEIGHTS[(int) $date->format(self::WEEKDAY_FORMAT)] ?? 1.0)
            * $peak;
    }

    /**
     * Splits a total in proportion to the weights, giving the units lost to rounding down to
     * the largest remainders, so the parts add up exactly.
     *
     * @param list<float> $weights
     * @return list<int>
     */
    private static function apportion(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        $exact = array_map(static fn (float $weight): float => $total * $weight / $sum, $weights);
        $parts = array_map(static fn (float $share): int => (int) floor($share), $exact);
        $remainders = array_map(static fn (float $share, int $part): float => $share - $part, $exact, $parts);
        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, $total - array_sum($parts)) as $index) {
            $parts[$index]++;
        }

        return array_values($parts);
    }
}
