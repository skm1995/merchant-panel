<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Message;

use App\Auth\Message\AccountExistsEmailMessage;
use PHPUnit\Framework\TestCase;

final class AccountExistsEmailMessageTest extends TestCase
{
    private const string EMAIL = 'person@example.com';

    public function testTravelsThroughTheOutboxUnderAStableTypeName(): void
    {
        $restored = AccountExistsEmailMessage::fromArray(new AccountExistsEmailMessage(self::EMAIL)->toArray());

        self::assertSame('auth.account_exists_email', AccountExistsEmailMessage::messageType());
        self::assertSame(self::EMAIL, $restored->email);
    }
}
