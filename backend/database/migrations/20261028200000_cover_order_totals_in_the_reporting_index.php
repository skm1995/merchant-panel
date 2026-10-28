<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Extends the reporting index with the currency and total, so the daily revenue report reads
 * only the index. On millions of orders MySQL otherwise preferred the status-and-expiry index
 * and read every paid order's row: 5 seconds for two months instead of a fraction of one.
 * The new index is built before the old one goes, so reports always have one.
 */
final class CoverOrderTotalsInTheReportingIndex extends AbstractMigration
{
    private const string ORDERS_TABLE = 'orders';
    private const array COVERING_COLUMNS = ['status', 'created_at', 'total_currency', 'total_amount'];
    private const array OLD_COLUMNS = ['status', 'created_at'];
    private const string COVERING_INDEX = 'orders_status_created_at_totals';
    private const string OLD_INDEX = 'orders_status_created_at';

    public function up(): void
    {
        $this->table(self::ORDERS_TABLE)->addIndex(self::COVERING_COLUMNS, ['name' => self::COVERING_INDEX])->update();
        $this->table(self::ORDERS_TABLE)->removeIndexByName(self::OLD_INDEX)->update();
    }

    public function down(): void
    {
        $this->table(self::ORDERS_TABLE)->addIndex(self::OLD_COLUMNS, ['name' => self::OLD_INDEX])->update();
        $this->table(self::ORDERS_TABLE)->removeIndexByName(self::COVERING_INDEX)->update();
    }
}
