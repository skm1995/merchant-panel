<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messenger;

use App\Auth\Message\PasswordResetEmailMessage;
use App\Shared\DependencyInjection\Env;
use App\Shared\Messaging\AsMessageHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class SendPasswordResetEmailHandler
{
    private const string SUBJECT = 'Reset your password';
    private const string BODY = "Set a new password by opening this link within an hour: %s\n\nIf you did not ask for it, ignore this email; your password stays as it is.";
    private const string RESET_URL_FORMAT = '%s/reset-password?token=%s';
    private const string URL_PATH_SEPARATOR = '/';

    public function __construct(
        private MailerInterface $mailer,
        #[Env('APP_BASE_URL')] private readonly string $baseUrl,
        #[Env('MAILER_FROM')] private readonly string $senderEmail,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(PasswordResetEmailMessage $message): void
    {
        $resetUrl = sprintf(
            self::RESET_URL_FORMAT,
            rtrim($this->baseUrl, self::URL_PATH_SEPARATOR),
            rawurlencode($message->token),
        );
        $email = new Email()
            ->from($this->senderEmail)
            ->to($message->email)
            ->subject(self::SUBJECT)
            ->text(sprintf(self::BODY, $resetUrl));

        $this->mailer->send($email);
    }
}
