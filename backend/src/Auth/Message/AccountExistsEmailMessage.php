<?php

declare(strict_types=1);

namespace App\Auth\Message;

use App\Shared\Messaging\Message;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;

/**
 * Tells the owner of an address that someone tried to register it again. Registration
 * answers the same either way, so it never reveals which addresses have accounts.
 */
#[Message(transport: 'emails')]
final readonly class AccountExistsEmailMessage implements OutboxMessageInterface
{
    private const string TYPE = 'auth.account_exists_email';
    private const string EMAIL_KEY = 'email';

    public function __construct(public string $email)
    {
    }

    public static function messageType(): string
    {
        return self::TYPE;
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data[self::EMAIL_KEY]);
    }

    public function toArray(): array
    {
        return [self::EMAIL_KEY => $this->email];
    }
}
