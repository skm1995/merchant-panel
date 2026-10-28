<?php

declare(strict_types=1);

namespace App\Analytics\Service;

use App\Analytics\Exception\SalesHistoryAlreadyExists;
use App\Analytics\Repository\SalesHistoryRepositoryInterface;
use App\Analytics\ValueObject\SalesCalendar;
use App\Shared\Database\TransactionRunnerInterface;
use Psr\Clock\ClockInterface;

/**
 * Fills the shop with three years of sales history, a day at a time, so the reports run on
 * millions of orders. bin/generate-sales-history.php runs it on demand.
 */
final readonly class GenerateSalesHistoryService
{
    public const int PRODUCTS = 1000;
    public const int BATCH = 1000;
    private const int DAYS = 1095;

    public function __construct(
        private SalesHistoryRepositoryInterface $history,
        private SalesHistoryFactory $factory,
        private TransactionRunnerInterface $transactions,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param callable(int $done, int $total): void $progress called after every day
     */
    public function execute(int $orders, callable $progress): void
    {
        if ($this->history->hasHistory()) {
            throw new SalesHistoryAlreadyExists();
        }

        $products = $this->factory->products(self::PRODUCTS);
        $productsById = array_combine($this->history->addProducts($products), $products);
        $nextNumber = 1;

        foreach (new SalesCalendar($this->clock->now(), self::DAYS)->distribute($orders) as $day) {
            foreach (array_chunk($this->factory->ordersForDay($day, $nextNumber, $productsById), self::BATCH) as $batch) {
                $this->transactions->run(fn () => $this->history->addOrders($batch));
            }

            $nextNumber += $day->orders;
            $progress($nextNumber - 1, $orders);
        }
    }
}
