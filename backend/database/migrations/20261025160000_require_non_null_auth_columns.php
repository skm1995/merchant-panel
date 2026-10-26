<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RequireNonNullAuthColumns extends AbstractMigration
{
    private const string USERS_TABLE = 'users';
    private const string TOKENS_TABLE = 'email_verification_tokens';
    private const string STRING_TYPE = 'string';
    private const string DATETIME_TYPE = 'datetime';
    private const string INTEGER_TYPE = 'integer';
    private const string CURRENT_TIMESTAMP = 'CURRENT_TIMESTAMP';

    public function up(): void
    {
        $this->changeNullability(false);
    }

    public function down(): void
    {
        $this->changeNullability(true);
    }

    private function changeNullability(bool $nullable): void
    {
        $this->table(self::USERS_TABLE)
            ->changeColumn('email', self::STRING_TYPE, ['limit' => 255, 'null' => $nullable])
            ->changeColumn('password', self::STRING_TYPE, ['limit' => 255, 'null' => $nullable])
            ->changeColumn('created_at', self::DATETIME_TYPE, ['default' => self::CURRENT_TIMESTAMP, 'null' => $nullable])
            ->update();

        $this->table(self::TOKENS_TABLE)
            ->changeColumn('email', self::STRING_TYPE, ['limit' => 255, 'null' => $nullable])
            ->changeColumn('token_hash', self::STRING_TYPE, ['limit' => 64, 'null' => $nullable])
            ->changeColumn('expires_at', self::DATETIME_TYPE, ['null' => $nullable])
            ->changeColumn('resend_count', self::INTEGER_TYPE, ['default' => 0, 'null' => $nullable])
            ->changeColumn(
                'resend_window_started_at',
                self::DATETIME_TYPE,
                ['default' => self::CURRENT_TIMESTAMP, 'null' => $nullable],
            )
            ->update();
    }
}
