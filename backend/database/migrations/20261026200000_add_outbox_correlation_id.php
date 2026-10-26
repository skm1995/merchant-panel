<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Keeps the request id of the request that queued each message, so the worker's logs about
 * it can be found together with the request's. NULL marks messages queued outside a request.
 */
final class AddOutboxCorrelationId extends AbstractMigration
{
    private const string OUTBOX_TABLE = 'outbox_messages';
    private const string CORRELATION_ID_COLUMN = 'correlation_id';
    private const string AFTER_COLUMN = 'payload';
    private const int MAXIMUM_LENGTH = 64;

    public function change(): void
    {
        $this->table(self::OUTBOX_TABLE)
            ->addColumn(self::CORRELATION_ID_COLUMN, 'string', ['limit' => self::MAXIMUM_LENGTH, 'null' => true, 'after' => self::AFTER_COLUMN])
            ->update();
    }
}
