<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Outbox;

use App\Shared\Database\RecordFetcher;
use App\Shared\Database\SqlDateTime;
use App\Shared\Database\SqlPlaceholders;
use App\Shared\Logging\CorrelationId;
use DateTimeImmutable;
use PDO;

final class PdoOutbox implements OutboxInterface, OutboxStoreInterface
{
    private const string INSERT_QUERY = 'INSERT INTO outbox_messages (message_type, payload, correlation_id, deliver_at) VALUES (:message_type, :payload, :correlation_id, :deliver_at)';
    private const string CLAIM_QUERY_FORMAT = <<<'SQL'
        SELECT id, message_type AS messageType, payload, correlation_id AS correlationId, deliver_at AS deliverAt
        FROM outbox_messages
        WHERE message_type IN (%s) AND (claimed_until IS NULL OR claimed_until < ?)
        ORDER BY id
        LIMIT ?
        FOR UPDATE SKIP LOCKED
        SQL;
    private const int FIRST_POSITION = 1;
    private const string LEASE_QUERY_FORMAT = 'UPDATE outbox_messages SET claimed_until = ? WHERE id IN (%s)';
    private const string REMOVE_QUERY = 'DELETE FROM outbox_messages WHERE id = :id';
    private const string MESSAGE_TYPE_PARAMETER = 'message_type';
    private const string PAYLOAD_PARAMETER = 'payload';
    private const string DELIVER_AT_PARAMETER = 'deliver_at';
    private const string CORRELATION_ID_PARAMETER = 'correlation_id';
    private const string ID_PARAMETER = 'id';

    public function __construct(private readonly PDO $pdo, private readonly CorrelationId $correlation)
    {
    }

    public function add(OutboxMessageInterface $message, DateTimeImmutable $deliverAt): void
    {
        $this->pdo->prepare(self::INSERT_QUERY)->execute([
            self::MESSAGE_TYPE_PARAMETER => $message::messageType(),
            self::PAYLOAD_PARAMETER => json_encode($message->toArray(), JSON_THROW_ON_ERROR),
            self::CORRELATION_ID_PARAMETER => $this->correlation->get(),
            self::DELIVER_AT_PARAMETER => SqlDateTime::format($deliverAt),
        ]);
    }

    public function claim(array $messageTypes, int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): array
    {
        $statement = $this->pdo->prepare(sprintf(self::CLAIM_QUERY_FORMAT, SqlPlaceholders::positional(count($messageTypes))));
        $position = self::FIRST_POSITION;

        foreach ($messageTypes as $messageType) {
            $statement->bindValue($position++, $messageType);
        }

        $statement->bindValue($position++, SqlDateTime::format($now));
        $statement->bindValue($position, $limit, PDO::PARAM_INT);
        $statement->execute();
        $claimed = array_map(
            static fn (OutboxRecord $record): ClaimedOutboxMessage => $record->toEntity(),
            RecordFetcher::all($statement, OutboxRecord::class),
        );
        if ($claimed !== []) {
            $this->pdo->prepare(sprintf(self::LEASE_QUERY_FORMAT, SqlPlaceholders::positional(count($claimed))))->execute([
                SqlDateTime::format($leaseUntil),
                ...array_map(static fn (ClaimedOutboxMessage $message): int => $message->id, $claimed),
            ]);
        }

        return $claimed;
    }

    public function remove(int $id): void
    {
        $this->pdo->prepare(self::REMOVE_QUERY)->execute([self::ID_PARAMETER => $id]);
    }
}
