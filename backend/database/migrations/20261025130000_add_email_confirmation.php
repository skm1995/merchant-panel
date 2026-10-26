<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddEmailConfirmation extends AbstractMigration
{
    private const string USERS_TABLE = 'users';
    private const string EMAIL_VERIFIED_AT_COLUMN = 'email_verified_at';
    private const string TOKENS_TABLE = 'email_verification_tokens';
    private const string EMAIL_COLUMN = 'email';
    private const string TOKEN_HASH_COLUMN = 'token_hash';
    private const string EXPIRES_AT_COLUMN = 'expires_at';
    private const string EMAIL_INDEX = 'email_verification_tokens_email_unique';
    private const string TOKEN_HASH_INDEX = 'email_verification_tokens_hash_unique';

    public function change(): void
    {
        $this->table(self::USERS_TABLE)
            ->addColumn(self::EMAIL_VERIFIED_AT_COLUMN, 'datetime', ['null' => true])
            ->update();

        $this->table(self::TOKENS_TABLE)
            ->addColumn(self::EMAIL_COLUMN, 'string', ['limit' => 255])
            ->addColumn(self::TOKEN_HASH_COLUMN, 'string', ['limit' => 64])
            ->addColumn(self::EXPIRES_AT_COLUMN, 'datetime')
            ->addIndex([self::EMAIL_COLUMN], ['unique' => true, 'name' => self::EMAIL_INDEX])
            ->addIndex([self::TOKEN_HASH_COLUMN], ['unique' => true, 'name' => self::TOKEN_HASH_INDEX])
            ->addForeignKey(self::EMAIL_COLUMN, self::USERS_TABLE, self::EMAIL_COLUMN, ['delete' => 'CASCADE'])
            ->create();
    }
}
