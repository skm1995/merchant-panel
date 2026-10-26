<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddOrderLifecycle extends AbstractMigration
{
    private const string ORDERS_TABLE = 'orders';
    private const string VERSION_COLUMN = 'version';
    private const string STATUS_COLUMN = 'status';
    private const string DROP_STATUS_CONSTRAINT = 'ALTER TABLE orders DROP CHECK orders_status_known';
    private const string LIFECYCLE_STATUS_CONSTRAINT = "ALTER TABLE orders ADD CONSTRAINT orders_status_known CHECK (status IN ('pending_payment', 'paid', 'cancelled', 'expired'))";
    private const string PENDING_STATUS_CONSTRAINT = "ALTER TABLE orders ADD CONSTRAINT orders_status_known CHECK (status IN ('pending_payment'))";

    public function up(): void
    {
        $this->table(self::ORDERS_TABLE)
            ->addColumn(self::VERSION_COLUMN, 'integer', [
                'signed' => false,
                'null' => false,
                'default' => 1,
                'after' => self::STATUS_COLUMN,
            ])
            ->update();

        $this->execute(self::DROP_STATUS_CONSTRAINT);
        $this->execute(self::LIFECYCLE_STATUS_CONSTRAINT);
    }

    public function down(): void
    {
        $this->execute(self::DROP_STATUS_CONSTRAINT);
        $this->execute(self::PENDING_STATUS_CONSTRAINT);
        $this->table(self::ORDERS_TABLE)->removeColumn(self::VERSION_COLUMN)->update();
    }
}
