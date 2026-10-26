<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messenger;

use App\Auth\Message\ConfirmationEmailMessage;
use App\Shared\DependencyInjection\Env;
use App\Shared\Messaging\AsMessageHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class SendConfirmationEmailHandler
{
    private const string SUBJECT = 'Confirm your email address';
    private const string BODY = 'Confirm your email address by opening this link: %s';
    private const string CONFIRMATION_URL_FORMAT = '%s/confirm?token=%s';
    private const string URL_PATH_SEPARATOR = '/';

    public function __construct(
        private MailerInterface $mailer,
        #[Env('APP_BASE_URL')] private readonly string $baseUrl,
        #[Env('MAILER_FROM')] private readonly string $senderEmail,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ConfirmationEmailMessage $message): void
    {
        $confirmationUrl = sprintf(
            self::CONFIRMATION_URL_FORMAT,
            rtrim($this->baseUrl, self::URL_PATH_SEPARATOR),
            rawurlencode($message->token),
        );
        $email = new Email()
            ->from($this->senderEmail)
            ->to($message->email)
            ->subject(self::SUBJECT)
            ->text(sprintf(self::BODY, $confirmationUrl));

        $this->mailer->send($email);
    }
}
