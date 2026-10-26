<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePayments extends AbstractMigration
{
    private const string PAYMENTS_TABLE = 'payments';
    private const string WEBHOOK_EVENTS_TABLE = 'payment_webhook_events';
    private const string ORDERS_TABLE = 'orders';
    private const string ID_COLUMN = 'id';
    private const string PUBLIC_ID_COLUMN = 'public_id';
    private const string ORDER_ID_COLUMN = 'order_id';
    private const string PROVIDER_COLUMN = 'provider';
    private const string PROVIDER_PAYMENT_ID_COLUMN = 'provider_payment_id';
    private const string CHECKOUT_URL_COLUMN = 'checkout_url';
    private const string AMOUNT_COLUMN = 'amount';
    private const string CURRENCY_COLUMN = 'currency';
    private const string STATUS_COLUMN = 'status';
    private const string IDEMPOTENCY_KEY_COLUMN = 'idempotency_key';
    private const string VERSION_COLUMN = 'version';
    private const string EVENT_ID_COLUMN = 'event_id';
    private const string RECEIVED_AT_COLUMN = 'received_at';
    private const string PUBLIC_ID_INDEX = 'payments_public_id_unique';
    private const string PROVIDER_PAYMENT_INDEX = 'payments_provider_payment_unique';
    private const string IDEMPOTENCY_KEY_INDEX = 'payments_idempotency_key_unique';
    private const string CURRENT_TIMESTAMP = 'CURRENT_TIMESTAMP';
    private const string STATUS_CONSTRAINT = "ALTER TABLE payments ADD CONSTRAINT payments_status_known CHECK (status IN ('pending', 'succeeded', 'failed', 'refund_required'))";

    public function up(): void
    {
        $this->table(self::PAYMENTS_TABLE, ['signed' => false])
            ->addColumn(self::PUBLIC_ID_COLUMN, 'binary', ['limit' => 16, 'null' => false])
            ->addColumn(self::ORDER_ID_COLUMN, 'integer', ['signed' => false, 'null' => false])
            ->addColumn(self::PROVIDER_COLUMN, 'string', ['limit' => 32, 'null' => false])
            ->addColumn(self::PROVIDER_PAYMENT_ID_COLUMN, 'string', ['limit' => 64, 'null' => false])
            ->addColumn(self::CHECKOUT_URL_COLUMN, 'string', ['limit' => 2048, 'null' => false])
            ->addColumn(self::AMOUNT_COLUMN, 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn(self::CURRENCY_COLUMN, 'char', ['limit' => 3, 'null' => false])
            ->addColumn(self::STATUS_COLUMN, 'string', ['limit' => 32, 'null' => false])
            ->addColumn(self::IDEMPOTENCY_KEY_COLUMN, 'string', ['limit' => 255, 'null' => false])
            ->addColumn(self::VERSION_COLUMN, 'integer', ['signed' => false, 'null' => false, 'default' => 1])
            ->addTimestamps()
            ->addIndex([self::PUBLIC_ID_COLUMN], ['unique' => true, 'name' => self::PUBLIC_ID_INDEX])
            ->addIndex(
                [self::PROVIDER_COLUMN, self::PROVIDER_PAYMENT_ID_COLUMN],
                ['unique' => true, 'name' => self::PROVIDER_PAYMENT_INDEX],
            )
            ->addIndex([self::IDEMPOTENCY_KEY_COLUMN], ['unique' => true, 'name' => self::IDEMPOTENCY_KEY_INDEX])
            ->addForeignKey(self::ORDER_ID_COLUMN, self::ORDERS_TABLE, self::ID_COLUMN, ['delete' => 'RESTRICT'])
            ->create();

        $this->table(self::WEBHOOK_EVENTS_TABLE, [
            'id' => false,
            'primary_key' => [self::PROVIDER_COLUMN, self::EVENT_ID_COLUMN],
        ])
            ->addColumn(self::PROVIDER_COLUMN, 'string', ['limit' => 32, 'null' => false])
            ->addColumn(self::EVENT_ID_COLUMN, 'string', ['limit' => 128, 'null' => false])
            ->addColumn(self::RECEIVED_AT_COLUMN, 'timestamp', ['null' => false, 'default' => self::CURRENT_TIMESTAMP])
            ->create();

        $this->execute(self::STATUS_CONSTRAINT);
    }

    public function down(): void
    {
        $this->table(self::WEBHOOK_EVENTS_TABLE)->drop()->save();
        $this->table(self::PAYMENTS_TABLE)->drop()->save();
    }
}
