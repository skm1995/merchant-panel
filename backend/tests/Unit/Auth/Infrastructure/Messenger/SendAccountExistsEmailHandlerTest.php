<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Messenger;

use App\Auth\Infrastructure\Messenger\SendAccountExistsEmailHandler;
use App\Auth\Message\AccountExistsEmailMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendAccountExistsEmailHandlerTest extends TestCase
{
    private const string RECIPIENT = 'person@example.com';
    private const string SENDER = 'no-reply@example.com';
    private const string BASE_URL = 'https://example.test/';
    private const string SIGN_IN_URL = 'https://example.test/login';
    private const string SUBJECT = 'You already have an account';

    public function testTellsTheOwnerToSignInInsteadOfRegisteringAgain(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(
            self::callback(static function (Email $email): bool {
                self::assertSame(self::RECIPIENT, $email->getTo()[0]->getAddress());
                self::assertSame(self::SENDER, $email->getFrom()[0]->getAddress());
                self::assertSame(self::SUBJECT, $email->getSubject());
                self::assertStringContainsString(self::SIGN_IN_URL, (string) $email->getTextBody());

                return true;
            }),
        );

        new SendAccountExistsEmailHandler($mailer, self::BASE_URL, self::SENDER)(new AccountExistsEmailMessage(self::RECIPIENT));
    }
}
