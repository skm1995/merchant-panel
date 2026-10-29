<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Messenger;

use App\Auth\Infrastructure\Messenger\SendConfirmationEmailHandler;
use App\Auth\Message\ConfirmationEmailMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendConfirmationEmailHandlerTest extends TestCase
{
    private const string RECIPIENT = 'person@example.com';
    private const string SENDER = 'no-reply@example.com';
    private const string TOKEN = 'secure-token';
    private const string BASE_URL = 'https://example.test';
    private const string CONFIRMATION_URL = 'https://example.test/confirm?token=secure-token';
    private const string SUBJECT = 'Confirm your email address';

    public function testSendsConfirmationEmailWithTheRecipientAndTokenLink(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(
            self::callback(static function (Email $email): bool {
                self::assertSame(self::RECIPIENT, $email->getTo()[0]->getAddress());
                self::assertSame(self::SENDER, $email->getFrom()[0]->getAddress());
                self::assertSame(self::SUBJECT, $email->getSubject());
                self::assertStringContainsString(self::CONFIRMATION_URL, (string) $email->getTextBody());

                return true;
            }),
        );
        $handler = new SendConfirmationEmailHandler($mailer, self::BASE_URL, self::SENDER);

        $handler(new ConfirmationEmailMessage(self::RECIPIENT, self::TOKEN));
    }
}
