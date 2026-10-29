<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * One password reset link per account: the SHA-256 hash of its token, when it expires, and
 * when it was created, which spaces out repeated requests.
 */
final class CreatePasswordResetTokens extends AbstractMigration
{
    private const string TOKENS_TABLE = 'password_reset_tokens';
    private const string USERS_TABLE = 'users';
    private const string EMAIL_COLUMN = 'email';
    private const string TOKEN_HASH_COLUMN = 'token_hash';
    private const string EXPIRES_AT_COLUMN = 'expires_at';
    private const string CREATED_AT_COLUMN = 'created_at';
    private const string EMAIL_INDEX = 'password_reset_tokens_email_unique';
    private const string TOKEN_HASH_INDEX = 'password_reset_tokens_hash_unique';

    public function change(): void
    {
        $this->table(self::TOKENS_TABLE)
            ->addColumn(self::EMAIL_COLUMN, 'string', ['limit' => 255])
            ->addColumn(self::TOKEN_HASH_COLUMN, 'string', ['limit' => 64])
            ->addColumn(self::EXPIRES_AT_COLUMN, 'datetime')
            ->addColumn(self::CREATED_AT_COLUMN, 'datetime')
            ->addIndex([self::EMAIL_COLUMN], ['unique' => true, 'name' => self::EMAIL_INDEX])
            ->addIndex([self::TOKEN_HASH_COLUMN], ['unique' => true, 'name' => self::TOKEN_HASH_INDEX])
            ->addForeignKey(self::EMAIL_COLUMN, self::USERS_TABLE, self::EMAIL_COLUMN, ['delete' => 'CASCADE'])
            ->create();
    }
}
