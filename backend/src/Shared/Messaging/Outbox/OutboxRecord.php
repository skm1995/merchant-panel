<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use App\Shared\Serialization\JsonDecoder;
use DateTimeImmutable;
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
        $payload = JsonDecoder::toArray($this->payload)
            ?? throw new UnexpectedValueException(sprintf(self::INVALID_PAYLOAD_FORMAT, $this->id));

        /** @var array<string, mixed> $payload */
        return new ClaimedOutboxMessage($this->id, $this->messageType, $payload, new DateTimeImmutable($this->deliverAt));
    }
}
