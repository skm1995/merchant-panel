<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCarts extends AbstractMigration
{
    private const string CARTS_TABLE = 'carts';
    private const string CART_ITEMS_TABLE = 'cart_items';
    private const string PRODUCTS_TABLE = 'products';
    private const string ID_COLUMN = 'id';
    private const string STATUS_COLUMN = 'status';
    private const string CART_ID_COLUMN = 'cart_id';
    private const string PRODUCT_ID_COLUMN = 'product_id';
    private const string QUANTITY_COLUMN = 'quantity';
    private const string STATUS_CONSTRAINT = "ALTER TABLE carts ADD CONSTRAINT carts_status_known CHECK (status IN ('open', 'checked_out'))";
    private const string QUANTITY_CONSTRAINT = 'ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_positive CHECK (quantity > 0)';

    public function up(): void
    {
        $this->table(self::CARTS_TABLE, ['id' => false, 'primary_key' => [self::ID_COLUMN]])
            ->addColumn(self::ID_COLUMN, 'binary', ['limit' => 16, 'null' => false])
            ->addColumn(self::STATUS_COLUMN, 'string', ['limit' => 20, 'null' => false])
            ->addTimestamps()
            ->create();

        $this->table(self::CART_ITEMS_TABLE, [
            'id' => false,
            'primary_key' => [self::CART_ID_COLUMN, self::PRODUCT_ID_COLUMN],
        ])
            ->addColumn(self::CART_ID_COLUMN, 'binary', ['limit' => 16, 'null' => false])
            ->addColumn(self::PRODUCT_ID_COLUMN, 'integer', ['signed' => false, 'null' => false])
            ->addColumn(self::QUANTITY_COLUMN, 'integer', ['signed' => false, 'null' => false])
            ->addForeignKey(self::CART_ID_COLUMN, self::CARTS_TABLE, self::ID_COLUMN, ['delete' => 'CASCADE'])
            ->addForeignKey(self::PRODUCT_ID_COLUMN, self::PRODUCTS_TABLE, self::ID_COLUMN, ['delete' => 'RESTRICT'])
            ->create();

        $this->execute(self::STATUS_CONSTRAINT);
        $this->execute(self::QUANTITY_CONSTRAINT);
    }

    public function down(): void
    {
        $this->table(self::CART_ITEMS_TABLE)->drop()->save();
        $this->table(self::CARTS_TABLE)->drop()->save();
    }
}
