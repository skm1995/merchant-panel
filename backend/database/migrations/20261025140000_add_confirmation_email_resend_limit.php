<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddConfirmationEmailResendLimit extends AbstractMigration
{
    private const string TOKENS_TABLE = 'email_verification_tokens';
    private const string RESEND_COUNT_COLUMN = 'resend_count';
    private const string RESEND_WINDOW_STARTED_AT_COLUMN = 'resend_window_started_at';

    public function change(): void
    {
        $this->table(self::TOKENS_TABLE)
            ->addColumn(self::RESEND_COUNT_COLUMN, 'integer', ['default' => 0])
            ->addColumn(
                self::RESEND_WINDOW_STARTED_AT_COLUMN,
                'datetime',
                ['default' => 'CURRENT_TIMESTAMP'],
            )
            ->update();
    }
}
