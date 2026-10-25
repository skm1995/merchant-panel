<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsers extends AbstractMigration
{
    private const string USERS_TABLE = 'users';
    private const string EMAIL_COLUMN = 'email';
    private const string PASSWORD_COLUMN = 'password';
    private const string CREATED_AT_COLUMN = 'created_at';

    public function change(): void
    {
        $this->table(self::USERS_TABLE)
            ->addColumn(self::EMAIL_COLUMN, 'string', ['limit' => 255])
            ->addColumn(self::PASSWORD_COLUMN, 'string', ['limit' => 255])
            ->addColumn(self::CREATED_AT_COLUMN, 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex([self::EMAIL_COLUMN], ['unique' => true])
            ->create();
    }
}
