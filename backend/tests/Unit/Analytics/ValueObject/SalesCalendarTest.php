<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics\ValueObject;

use App\Analytics\ValueObject\SalesCalendar;
use App\Analytics\ValueObject\SalesDay;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SalesCalendarTest extends TestCase
{
    private const string TODAY = '2026-10-27';
    private const int DAYS = 1095;
    private const int ORDERS = 300000;
    private const string BLACK_FRIDAY_2025 = '2025-11-28';
    private const string ORDINARY_DAY_2025 = '2025-10-14';
    private const string DATE_FORMAT = 'Y-m-d';

    public function testSpreadsExactlyTheRequestedOrdersOverTheDaysBeforeToday(): void
    {
        $days = $this->calendar()->distribute(self::ORDERS);

        self::assertCount(self::DAYS, $days);
        self::assertSame(self::ORDERS, array_sum(array_map(static fn (SalesDay $day): int => $day->orders, $days)));
        self::assertSame('2026-10-26', $days[self::DAYS - 1]->date->format(self::DATE_FORMAT), 'History ends yesterday, so it never mixes with live orders.');
        self::assertSame('2023-10-28', $days[0]->date->format(self::DATE_FORMAT));
    }

    public function testBlackFridayIsTheBusiestDayOfItsYear(): void
    {
        $orders = $this->ordersByDate();
        $year2025 = array_filter($orders, static fn (string $date): bool => str_starts_with($date, '2025-'), ARRAY_FILTER_USE_KEY);

        arsort($year2025);

        self::assertSame(self::BLACK_FRIDAY_2025, array_key_first($year2025));
        self::assertGreaterThan(3 * $orders[self::ORDINARY_DAY_2025], $orders[self::BLACK_FRIDAY_2025]);
    }

    public function testTheShopGrowsFromYearToYear(): void
    {
        $orders = $this->ordersByDate();
        $total = static fn (string $from, string $to): int => array_sum(array_filter(
            $orders,
            static fn (string $date): bool => $date >= $from && $date <= $to,
            ARRAY_FILTER_USE_KEY,
        ));

        self::assertGreaterThan($total('2023-11-01', '2024-10-31'), $total('2025-11-01', '2026-10-26'));
    }

    private function calendar(): SalesCalendar
    {
        return new SalesCalendar(new DateTimeImmutable(self::TODAY), self::DAYS);
    }

    /** @return array<string, int> */
    private function ordersByDate(): array
    {
        $orders = [];
        foreach ($this->calendar()->distribute(self::ORDERS) as $day) {
            $orders[$day->date->format(self::DATE_FORMAT)] = $day->orders;
        }

        return $orders;
    }
}
