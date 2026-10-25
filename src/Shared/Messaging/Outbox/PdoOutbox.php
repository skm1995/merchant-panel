<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use App\Shared\Database\RecordFetcher;
use DateTimeImmutable;
use PDO;

final class PdoOutbox implements OutboxInterface, OutboxStoreInterface
{
    private const string INSERT_QUERY = 'INSERT INTO outbox_messages (message_type, payload, deliver_at) VALUES (:message_type, :payload, :deliver_at)';
    private const string CLAIM_QUERY_FORMAT = <<<'SQL'
        SELECT id, message_type AS messageType, payload, deliver_at AS deliverAt
        FROM outbox_messages
        WHERE message_type IN (%s) AND (claimed_until IS NULL OR claimed_until < ?)
        ORDER BY id
        LIMIT ?
        FOR UPDATE SKIP LOCKED
        SQL;
    private const string POSITIONAL_PLACEHOLDER = '?';
    private const string PLACEHOLDER_SEPARATOR = ', ';
    private const int FIRST_POSITION = 1;
    private const string LEASE_QUERY = 'UPDATE outbox_messages SET claimed_until = :claimed_until WHERE id = :id';
    private const string REMOVE_QUERY = 'DELETE FROM outbox_messages WHERE id = :id';
    private const string MESSAGE_TYPE_PARAMETER = 'message_type';
    private const string PAYLOAD_PARAMETER = 'payload';
    private const string DELIVER_AT_PARAMETER = 'deliver_at';
    private const string CLAIMED_UNTIL_PARAMETER = 'claimed_until';
    private const string ID_PARAMETER = 'id';
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(OutboxMessageInterface $message, DateTimeImmutable $deliverAt): void
    {
        $this->pdo->prepare(self::INSERT_QUERY)->execute([
            self::MESSAGE_TYPE_PARAMETER => $message::messageType(),
            self::PAYLOAD_PARAMETER => json_encode($message->toArray(), JSON_THROW_ON_ERROR),
            self::DELIVER_AT_PARAMETER => $deliverAt->format(self::DATETIME_FORMAT),
        ]);
    }

    public function claim(array $messageTypes, int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): array
    {
        $placeholders = implode(
            self::PLACEHOLDER_SEPARATOR,
            array_fill(0, count($messageTypes), self::POSITIONAL_PLACEHOLDER),
        );
        $statement = $this->pdo->prepare(sprintf(self::CLAIM_QUERY_FORMAT, $placeholders));
        $position = self::FIRST_POSITION;

        foreach ($messageTypes as $messageType) {
            $statement->bindValue($position++, $messageType);
        }

        $statement->bindValue($position++, $now->format(self::DATETIME_FORMAT));
        $statement->bindValue($position, $limit, PDO::PARAM_INT);
        $statement->execute();
        $claimed = array_map(
            static fn (OutboxRecord $record): ClaimedOutboxMessage => $record->toEntity(),
            RecordFetcher::all($statement, OutboxRecord::class),
        );
        $lease = $this->pdo->prepare(self::LEASE_QUERY);

        foreach ($claimed as $message) {
            $lease->execute([
                self::CLAIMED_UNTIL_PARAMETER => $leaseUntil->format(self::DATETIME_FORMAT),
                self::ID_PARAMETER => $message->id,
            ]);
        }

        return $claimed;
    }

    public function remove(int $id): void
    {
        $this->pdo->prepare(self::REMOVE_QUERY)->execute([self::ID_PARAMETER => $id]);
    }
}
