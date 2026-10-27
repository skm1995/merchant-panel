<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Records when an account was given access to the merchant panel. NULL means it has none:
 * registering and confirming an email address no longer opens the panel by itself.
 */
final class AddUserMerchantSince extends AbstractMigration
{
    private const string USERS_TABLE = 'users';
    private const string MERCHANT_SINCE_COLUMN = 'merchant_since';
    private const string AFTER_COLUMN = 'email_verified_at';

    public function change(): void
    {
        $this->table(self::USERS_TABLE)
            ->addColumn(self::MERCHANT_SINCE_COLUMN, 'datetime', ['null' => true, 'after' => self::AFTER_COLUMN])
            ->update();
    }
}
