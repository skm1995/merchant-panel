<?php

declare(strict_types=1);

namespace App\Analytics\Service;

use App\Analytics\Repository\SalesHistoryRepositoryInterface;

/**
 * Removes the generated sales history and gives its disk space back. Orders go in batches,
 * so each transaction stays short and the shop keeps running meanwhile.
 * bin/remove-sales-history.php runs it on demand.
 */
final readonly class RemoveSalesHistoryService
{
    public const int BATCH = 20000;

    public function __construct(private SalesHistoryRepositoryInterface $history)
    {
    }

    /**
     * @param callable(int $removed): void $progress called after every batch
     * @return int the number of orders removed
     */
    public function execute(callable $progress): int
    {
        $removed = 0;

        while (($batch = $this->history->removeBatch(self::BATCH)) > 0) {
            $removed += $batch;
            $progress($removed);
        }

        $this->history->removeUnusedProducts();
        $this->history->reclaimSpace();

        return $removed;
    }
}
