<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Message;

use App\Auth\Message\PasswordResetEmailMessage;
use PHPUnit\Framework\TestCase;

final class PasswordResetEmailMessageTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string TOKEN = 'reset-token';

    public function testTravelsThroughTheOutboxUnderAStableTypeName(): void
    {
        $restored = PasswordResetEmailMessage::fromArray(new PasswordResetEmailMessage(self::EMAIL, self::TOKEN)->toArray());

        self::assertSame('auth.password_reset_email', PasswordResetEmailMessage::messageType());
        self::assertSame(self::EMAIL, $restored->email);
        self::assertSame(self::TOKEN, $restored->token);
    }
}
