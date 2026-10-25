<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddOrderReportingIndex extends AbstractMigration
{
    private const string ORDERS_TABLE = 'orders';
    private const string STATUS_COLUMN = 'status';
    private const string CREATED_AT_COLUMN = 'created_at';
    private const string REPORTING_INDEX = 'orders_status_created_at';

    public function change(): void
    {
        $this->table(self::ORDERS_TABLE)
            ->addIndex([self::STATUS_COLUMN, self::CREATED_AT_COLUMN], ['name' => self::REPORTING_INDEX])
            ->update();
    }
}
