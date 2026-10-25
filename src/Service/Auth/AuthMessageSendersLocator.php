<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

final class AuthMessageSendersLocator implements SendersLocatorInterface
{
    public function __construct(private readonly SenderInterface $sender)
    {
    }

    /** @return iterable<string, SenderInterface> */
    public function getSenders(Envelope $envelope): iterable
    {
        if ($envelope->getMessage() instanceof ConfirmationEmailMessage) {
            yield 'emails' => $this->sender;
        }
    }
}
