<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Auth;

use App\Service\Auth\AuthMessageSendersLocator;
use App\Service\Auth\ConfirmationEmailMessage;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

final class AuthMessageSendersLocatorTest extends TestCase
{
    private const string EMAIL_ALIAS = 'emails';
    private const string RECIPIENT = 'person@example.com';
    private const string TOKEN = 'confirmation-token';

    public function testRoutesConfirmationEmailMessagesToEmailTransport(): void
    {
        $sender = $this->createStub(SenderInterface::class);
        $locator = new AuthMessageSendersLocator($sender);

        $senders = iterator_to_array($locator->getSenders(
            new Envelope(new ConfirmationEmailMessage(self::RECIPIENT, self::TOKEN)),
        ));

        self::assertSame([self::EMAIL_ALIAS => $sender], $senders);
    }

    public function testLeavesOtherMessageTypesWithoutAnEmailTransport(): void
    {
        $sender = $this->createStub(SenderInterface::class);
        $locator = new AuthMessageSendersLocator($sender);

        self::assertSame([], iterator_to_array($locator->getSenders(new Envelope(new stdClass()))));
    }
}
