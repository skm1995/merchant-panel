<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Units and revenue of paid orders per day and product, so reports over months or years read
 * a few thousand summary rows instead of millions of order lines. The key starts with the
 * day, so reports for a period read one range of it.
 */
final class CreateDailyProductSales extends AbstractMigration
{
    private const string TABLE = 'daily_product_sales';
    private const string PRODUCTS_TABLE = 'products';
    private const string DAY_COLUMN = 'day';
    private const string PRODUCT_ID_COLUMN = 'product_id';
    private const string CURRENCY_COLUMN = 'currency';
    private const string UNITS_COLUMN = 'units';
    private const string REVENUE_COLUMN = 'revenue';
    private const string ID_COLUMN = 'id';

    public function change(): void
    {
        $this->table(self::TABLE, [
            'id' => false,
            'primary_key' => [self::DAY_COLUMN, self::PRODUCT_ID_COLUMN, self::CURRENCY_COLUMN],
        ])
            ->addColumn(self::DAY_COLUMN, 'date', ['null' => false])
            ->addColumn(self::PRODUCT_ID_COLUMN, 'integer', ['signed' => false, 'null' => false])
            ->addColumn(self::CURRENCY_COLUMN, 'char', ['limit' => 3, 'null' => false])
            ->addColumn(self::UNITS_COLUMN, 'integer', ['signed' => false, 'null' => false])
            ->addColumn(self::REVENUE_COLUMN, 'biginteger', ['signed' => false, 'null' => false])
            ->addForeignKey(self::PRODUCT_ID_COLUMN, self::PRODUCTS_TABLE, self::ID_COLUMN, ['delete' => 'CASCADE'])
            ->create();
    }
}
