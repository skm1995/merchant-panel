<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateProducts extends AbstractMigration
{
    private const string PRODUCTS_TABLE = 'products';
    private const string SKU_COLUMN = 'sku';
    private const string NAME_COLUMN = 'name';
    private const string PRICE_AMOUNT_COLUMN = 'price_amount';
    private const string PRICE_CURRENCY_COLUMN = 'price_currency';
    private const string STOCK_COLUMN = 'stock';
    private const string RESERVED_COLUMN = 'reserved';
    private const string VERSION_COLUMN = 'version';
    private const string SKU_INDEX = 'products_sku_unique';
    private const string RESERVED_WITHIN_STOCK_CONSTRAINT = 'ALTER TABLE products ADD CONSTRAINT products_reserved_within_stock CHECK (reserved <= stock)';

    public function up(): void
    {
        $this->table(self::PRODUCTS_TABLE, ['signed' => false])
            ->addColumn(self::SKU_COLUMN, 'string', ['limit' => 64, 'null' => false])
            ->addColumn(self::NAME_COLUMN, 'string', ['limit' => 255, 'null' => false])
            ->addColumn(self::PRICE_AMOUNT_COLUMN, 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn(self::PRICE_CURRENCY_COLUMN, 'char', ['limit' => 3, 'null' => false])
            ->addColumn(self::STOCK_COLUMN, 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn(self::RESERVED_COLUMN, 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn(self::VERSION_COLUMN, 'integer', ['signed' => false, 'default' => 1, 'null' => false])
            ->addTimestamps()
            ->addIndex([self::SKU_COLUMN], ['unique' => true, 'name' => self::SKU_INDEX])
            ->create();

        $this->execute(self::RESERVED_WITHIN_STOCK_CONSTRAINT);
    }

    public function down(): void
    {
        $this->table(self::PRODUCTS_TABLE)->drop()->save();
    }
}
