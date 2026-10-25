<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Mail;

use App\Shared\DependencyInjection\Env;
use App\Shared\DependencyInjection\ServiceFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

final class MailerFactory
{
    #[ServiceFactory]
    public static function create(#[Env('MAILER_DSN')] string $dsn): MailerInterface
    {
        return new Mailer(Transport::fromDsn($dsn));
    }
}
