<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics\Service;

use App\Analytics\Service\RemoveSalesHistoryService;
use PHPUnit\Framework\TestCase;

final class RemoveSalesHistoryServiceTest extends TestCase
{
    public function testRemovesOrdersInBatchesThenProductsAndGivesTheSpaceBack(): void
    {
        $history = new RecordingSalesHistoryRepository();
        $history->batches = [RemoveSalesHistoryService::BATCH, RemoveSalesHistoryService::BATCH, 1200];
        $history->removedProducts = 1000;
        $progress = [];

        $removed = new RemoveSalesHistoryService($history)->execute(static function (int $orders) use (&$progress): void {
            $progress[] = $orders;
        });

        self::assertSame(2 * RemoveSalesHistoryService::BATCH + 1200, $removed);
        self::assertSame([RemoveSalesHistoryService::BATCH, 2 * RemoveSalesHistoryService::BATCH, $removed], $progress);
        self::assertSame([], $history->batches);
        self::assertTrue($history->reclaimed);
    }

    public function testStillGivesTheSpaceBackWhenNoHistoryIsLeft(): void
    {
        $history = new RecordingSalesHistoryRepository();

        self::assertSame(0, new RemoveSalesHistoryService($history)->execute(static function (): void {
        }));
        self::assertTrue($history->reclaimed);
    }
}
