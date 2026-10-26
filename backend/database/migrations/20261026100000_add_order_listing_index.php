<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Lets the merchant panel page through orders newest first. InnoDB appends the primary key
 * to every secondary index, so this index also orders ties by id.
 */
final class AddOrderListingIndex extends AbstractMigration
{
    private const string ORDERS_TABLE = 'orders';
    private const string CREATED_AT_COLUMN = 'created_at';
    private const string LISTING_INDEX = 'orders_created_at';

    public function change(): void
    {
        $this->table(self::ORDERS_TABLE)
            ->addIndex([self::CREATED_AT_COLUMN], ['name' => self::LISTING_INDEX])
            ->update();
    }
}
