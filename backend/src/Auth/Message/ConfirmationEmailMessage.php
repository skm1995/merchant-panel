<?php

declare(strict_types=1);

namespace App\Auth\Message;

use App\Shared\Messaging\Message;
use App\Shared\Messaging\Outbox\OutboxMessageInterface;

/**
 * Carries the raw confirmation token to the worker. The outbox row holding it is deleted
 * as soon as the relay publishes it; the database keeps only the token hash.
 */
#[Message(transport: 'emails')]
final readonly class ConfirmationEmailMessage implements OutboxMessageInterface
{
    private const string TYPE = 'auth.confirmation_email';
    private const string EMAIL_KEY = 'email';
    private const string TOKEN_KEY = 'token';

    public function __construct(public string $email, public string $token)
    {
    }

    public static function messageType(): string
    {
        return self::TYPE;
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data[self::EMAIL_KEY], (string) $data[self::TOKEN_KEY]);
    }

    public function toArray(): array
    {
        return [self::EMAIL_KEY => $this->email, self::TOKEN_KEY => $this->token];
    }
}
