<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics\Service;

use App\Analytics\Exception\SalesHistoryAlreadyExists;
use App\Analytics\Service\GenerateSalesHistoryService;
use App\Analytics\Service\SalesHistoryFactory;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\ImmediateTransactionRunner;

final class GenerateSalesHistoryServiceTest extends TestCase
{
    private const string NOW = '2026-10-27 20:00:00';
    private const int ORDERS = 30000;

    public function testStoresTheRequestedOrdersNumberedContinuouslyInBoundedBatches(): void
    {
        $history = new RecordingSalesHistoryRepository();
        $progress = [];

        $this->service($history)->execute(self::ORDERS, static function (int $done, int $total) use (&$progress): void {
            $progress[] = [$done, $total];
        });

        $keys = array_merge(...$history->orderBatches);
        self::assertSame(GenerateSalesHistoryService::PRODUCTS, $history->products);
        self::assertSame(
            array_map(static fn (int $number): string => SalesHistoryFactory::KEY_PREFIX . $number, range(1, self::ORDERS)),
            $keys,
        );
        self::assertSame([], array_filter(
            $history->orderBatches,
            static fn (array $batch): bool => count($batch) > GenerateSalesHistoryService::BATCH,
        ), 'No batch holds more orders than one statement should insert.');
        self::assertSame([self::ORDERS, self::ORDERS], end($progress));
    }

    public function testRefusesToGenerateASecondHistory(): void
    {
        $history = new RecordingSalesHistoryRepository();
        $history->exists = true;

        try {
            $this->service($history)->execute(self::ORDERS, static function (): void {
            });
            self::fail('A second history must be refused.');
        } catch (SalesHistoryAlreadyExists) {
            self::assertSame(0, $history->products);
            self::assertSame([], $history->orderBatches);
        }
    }

    private function service(RecordingSalesHistoryRepository $history): GenerateSalesHistoryService
    {
        return new GenerateSalesHistoryService($history, new SalesHistoryFactory(), new ImmediateTransactionRunner(), new FrozenClock(self::NOW));
    }
}
