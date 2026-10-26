<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use App\Shared\Messaging\Exception\UnknownOutboxMessageTypeException;
use LogicException;

/**
 * Maps stable outbox type names back to the discovered outbox message classes.
 */
final class OutboxMessageTypes
{
    private const string DUPLICATE_TYPE_FORMAT = 'Outbox message type "%s" is registered twice.';

    /** @var array<string, class-string<OutboxMessageInterface>> */
    private array $classes = [];

    /** @param list<class-string<OutboxMessageInterface>> $messageClasses */
    public function __construct(array $messageClasses)
    {
        foreach ($messageClasses as $class) {
            $type = $class::messageType();

            if (isset($this->classes[$type])) {
                throw new LogicException(sprintf(self::DUPLICATE_TYPE_FORMAT, $type));
            }

            $this->classes[$type] = $class;
        }
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->classes);
    }

    /** @param array<string, mixed> $payload */
    public function restore(string $type, array $payload): OutboxMessageInterface
    {
        $class = $this->classes[$type] ?? throw new UnknownOutboxMessageTypeException($type);

        return $class::fromArray($payload);
    }
}
