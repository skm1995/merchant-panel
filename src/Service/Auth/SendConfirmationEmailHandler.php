<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendConfirmationEmailHandler
{
    private const string SUBJECT = 'Confirm your email address';
    private const string SENDER_EMAIL = 'no-reply@example.com';
    private const string BODY = 'Confirm your email address by opening this link: %s';
    private const string CONFIRMATION_PATH = '/confirm?token=%s';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $baseUrl,
    ) {
    }

    public function __invoke(ConfirmationEmailMessage $message): void
    {
        $encodedToken = $message->token |> rawurlencode(...);
        $confirmationPath = sprintf(self::CONFIRMATION_PATH, $encodedToken);
        $confirmationUrl = $this->baseUrl |> (
            static fn (string $baseUrl): string =>
            rtrim($baseUrl, '/') . $confirmationPath
        );
        $email = new Email()
            ->from(self::SENDER_EMAIL)
            ->to($message->email)
            ->subject(self::SUBJECT)
            ->text(sprintf(self::BODY, $confirmationUrl));

        $this->mailer->send($email);
    }
}
