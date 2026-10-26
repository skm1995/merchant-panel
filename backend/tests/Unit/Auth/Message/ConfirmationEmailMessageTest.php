<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Message;

use App\Auth\Message\ConfirmationEmailMessage;
use PHPUnit\Framework\TestCase;

final class ConfirmationEmailMessageTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string TOKEN = 'confirmation-token';

    public function testTravelsThroughTheOutboxUnderAStableTypeName(): void
    {
        $restored = ConfirmationEmailMessage::fromArray(new ConfirmationEmailMessage(self::EMAIL, self::TOKEN)->toArray());

        self::assertSame('auth.confirmation_email', ConfirmationEmailMessage::messageType());
        self::assertSame(self::EMAIL, $restored->email);
        self::assertSame(self::TOKEN, $restored->token);
    }
}
