<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOutboxMessages extends AbstractMigration
{
    private const string OUTBOX_TABLE = 'outbox_messages';
    private const string MESSAGE_TYPE_COLUMN = 'message_type';
    private const string PAYLOAD_COLUMN = 'payload';
    private const string DELIVER_AT_COLUMN = 'deliver_at';
    private const string CLAIMED_UNTIL_COLUMN = 'claimed_until';
    private const string CREATED_AT_COLUMN = 'created_at';
    private const string CURRENT_TIMESTAMP = 'CURRENT_TIMESTAMP';

    public function change(): void
    {
        $this->table(self::OUTBOX_TABLE, ['id' => true, 'signed' => false])
            ->addColumn(self::MESSAGE_TYPE_COLUMN, 'string', ['limit' => 100, 'null' => false])
            ->addColumn(self::PAYLOAD_COLUMN, 'json', ['null' => false])
            ->addColumn(self::DELIVER_AT_COLUMN, 'datetime', ['null' => false])
            ->addColumn(self::CLAIMED_UNTIL_COLUMN, 'datetime', ['null' => true])
            ->addColumn(self::CREATED_AT_COLUMN, 'timestamp', ['null' => false, 'default' => self::CURRENT_TIMESTAMP])
            ->create();
    }
}
