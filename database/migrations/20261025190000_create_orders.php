<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOrders extends AbstractMigration
{
    private const string ORDERS_TABLE = 'orders';
    private const string ORDER_ITEMS_TABLE = 'order_items';
    private const string CARTS_TABLE = 'carts';
    private const string PRODUCTS_TABLE = 'products';
    private const string ID_COLUMN = 'id';
    private const string PUBLIC_ID_COLUMN = 'public_id';
    private const string CART_ID_COLUMN = 'cart_id';
    private const string IDEMPOTENCY_KEY_COLUMN = 'idempotency_key';
    private const string STATUS_COLUMN = 'status';
    private const string TOTAL_AMOUNT_COLUMN = 'total_amount';
    private const string TOTAL_CURRENCY_COLUMN = 'total_currency';
    private const string RESERVATION_EXPIRES_AT_COLUMN = 'reservation_expires_at';
    private const string ORDER_ID_COLUMN = 'order_id';
    private const string PRODUCT_ID_COLUMN = 'product_id';
    private const string SKU_COLUMN = 'sku';
    private const string NAME_COLUMN = 'name';
    private const string QUANTITY_COLUMN = 'quantity';
    private const string UNIT_PRICE_AMOUNT_COLUMN = 'unit_price_amount';
    private const string UNIT_PRICE_CURRENCY_COLUMN = 'unit_price_currency';
    private const string PUBLIC_ID_INDEX = 'orders_public_id_unique';
    private const string CART_ID_INDEX = 'orders_cart_id_unique';
    private const string IDEMPOTENCY_KEY_INDEX = 'orders_idempotency_key_unique';
    private const string RESERVATION_EXPIRY_INDEX = 'orders_status_reservation_expiry';
    private const string STATUS_CONSTRAINT = "ALTER TABLE orders ADD CONSTRAINT orders_status_known CHECK (status IN ('pending_payment'))";
    private const string QUANTITY_CONSTRAINT = 'ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)';

    public function up(): void
    {
        $this->table(self::ORDERS_TABLE, ['signed' => false])
            ->addColumn(self::PUBLIC_ID_COLUMN, 'binary', ['limit' => 16, 'null' => false])
            ->addColumn(self::CART_ID_COLUMN, 'binary', ['limit' => 16, 'null' => false])
            ->addColumn(self::IDEMPOTENCY_KEY_COLUMN, 'string', ['limit' => 255, 'null' => false])
            ->addColumn(self::STATUS_COLUMN, 'string', ['limit' => 32, 'null' => false])
            ->addColumn(self::TOTAL_AMOUNT_COLUMN, 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn(self::TOTAL_CURRENCY_COLUMN, 'char', ['limit' => 3, 'null' => false])
            ->addColumn(self::RESERVATION_EXPIRES_AT_COLUMN, 'datetime', ['null' => false])
            ->addTimestamps()
            ->addIndex([self::PUBLIC_ID_COLUMN], ['unique' => true, 'name' => self::PUBLIC_ID_INDEX])
            ->addIndex([self::CART_ID_COLUMN], ['unique' => true, 'name' => self::CART_ID_INDEX])
            ->addIndex([self::IDEMPOTENCY_KEY_COLUMN], ['unique' => true, 'name' => self::IDEMPOTENCY_KEY_INDEX])
            ->addIndex(
                [self::STATUS_COLUMN, self::RESERVATION_EXPIRES_AT_COLUMN],
                ['name' => self::RESERVATION_EXPIRY_INDEX],
            )
            ->addForeignKey(self::CART_ID_COLUMN, self::CARTS_TABLE, self::ID_COLUMN, ['delete' => 'RESTRICT'])
            ->create();

        $this->table(self::ORDER_ITEMS_TABLE, [
            'id' => false,
            'primary_key' => [self::ORDER_ID_COLUMN, self::PRODUCT_ID_COLUMN],
        ])
            ->addColumn(self::ORDER_ID_COLUMN, 'integer', ['signed' => false, 'null' => false])
            ->addColumn(self::PRODUCT_ID_COLUMN, 'integer', ['signed' => false, 'null' => false])
            ->addColumn(self::SKU_COLUMN, 'string', ['limit' => 64, 'null' => false])
            ->addColumn(self::NAME_COLUMN, 'string', ['limit' => 255, 'null' => false])
            ->addColumn(self::QUANTITY_COLUMN, 'integer', ['signed' => false, 'null' => false])
            ->addColumn(self::UNIT_PRICE_AMOUNT_COLUMN, 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn(self::UNIT_PRICE_CURRENCY_COLUMN, 'char', ['limit' => 3, 'null' => false])
            ->addForeignKey(self::ORDER_ID_COLUMN, self::ORDERS_TABLE, self::ID_COLUMN, ['delete' => 'CASCADE'])
            ->addForeignKey(self::PRODUCT_ID_COLUMN, self::PRODUCTS_TABLE, self::ID_COLUMN, ['delete' => 'RESTRICT'])
            ->create();

        $this->execute(self::STATUS_CONSTRAINT);
        $this->execute(self::QUANTITY_CONSTRAINT);
    }

    public function down(): void
    {
        $this->table(self::ORDER_ITEMS_TABLE)->drop()->save();
        $this->table(self::ORDERS_TABLE)->drop()->save();
    }
}
