<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use DateTimeImmutable;
use JsonException;
use UnexpectedValueException;

/** @implements EntityRecord<ClaimedOutboxMessage> */
#[HydratedRecord]
final readonly class OutboxRecord implements EntityRecord
{
    private const string INVALID_PAYLOAD_FORMAT = 'Outbox message %d has a payload that is not a JSON object.';

    public int $id;
    public string $messageType;
    public string $payload;
    public string $deliverAt;

    public function toEntity(): ClaimedOutboxMessage
    {
        try {
            $payload = json_decode($this->payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException(sprintf(self::INVALID_PAYLOAD_FORMAT, $this->id), previous: $exception);
        }

        if (!is_array($payload)) {
            throw new UnexpectedValueException(sprintf(self::INVALID_PAYLOAD_FORMAT, $this->id));
        }

        /** @var array<string, mixed> $payload */
        return new ClaimedOutboxMessage($this->id, $this->messageType, $payload, new DateTimeImmutable($this->deliverAt));
    }
}
