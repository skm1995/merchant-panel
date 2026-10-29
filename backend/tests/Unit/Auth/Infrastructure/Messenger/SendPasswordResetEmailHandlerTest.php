<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Messenger;

use App\Auth\Infrastructure\Messenger\SendPasswordResetEmailHandler;
use App\Auth\Message\PasswordResetEmailMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendPasswordResetEmailHandlerTest extends TestCase
{
    private const string RECIPIENT = 'person@example.com';
    private const string SENDER = 'no-reply@example.com';
    private const string TOKEN = 'reset-token';
    private const string BASE_URL = 'https://example.test/';
    private const string RESET_URL = 'https://example.test/reset-password?token=reset-token';
    private const string SUBJECT = 'Reset your password';

    public function testSendsTheResetLinkToTheAccountOwner(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(
            self::callback(static function (Email $email): bool {
                self::assertSame(self::RECIPIENT, $email->getTo()[0]->getAddress());
                self::assertSame(self::SENDER, $email->getFrom()[0]->getAddress());
                self::assertSame(self::SUBJECT, $email->getSubject());
                self::assertStringContainsString(self::RESET_URL, (string) $email->getTextBody());

                return true;
            }),
        );
        $handler = new SendPasswordResetEmailHandler($mailer, self::BASE_URL, self::SENDER);

        $handler(new PasswordResetEmailMessage(self::RECIPIENT, self::TOKEN));
    }
}
