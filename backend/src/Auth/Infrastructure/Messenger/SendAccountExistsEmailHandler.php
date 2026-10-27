<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messenger;

use App\Auth\Message\AccountExistsEmailMessage;
use App\Shared\DependencyInjection\Env;
use App\Shared\Messaging\AsMessageHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class SendAccountExistsEmailHandler
{
    private const string SUBJECT = 'You already have an account';
    private const string BODY = "Someone tried to create an account with this email address, which already has one.\nSign in at %s. If it was not you, you can ignore this email.";
    private const string SIGN_IN_URL_FORMAT = '%s/login';
    private const string URL_PATH_SEPARATOR = '/';

    public function __construct(
        private MailerInterface $mailer,
        #[Env('APP_BASE_URL')] private readonly string $baseUrl,
        #[Env('MAILER_FROM')] private readonly string $senderEmail,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(AccountExistsEmailMessage $message): void
    {
        $signInUrl = sprintf(self::SIGN_IN_URL_FORMAT, rtrim($this->baseUrl, self::URL_PATH_SEPARATOR));
        $email = new Email()
            ->from($this->senderEmail)
            ->to($message->email)
            ->subject(self::SUBJECT)
            ->text(sprintf(self::BODY, $signInUrl));

        $this->mailer->send($email);
    }
}
