<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Payment\Enum\PaymentProviderEnum;
use App\Shared\Database\MysqlError;
use PDO;
use PDOException;

final class PdoWebhookInboxRepository implements WebhookInboxRepositoryInterface
{
    private const string RECORD_QUERY = 'INSERT INTO payment_webhook_events (provider, event_id) VALUES (:provider, :event_id)';
    private const string PROVIDER_PARAMETER = 'provider';
    private const string EVENT_ID_PARAMETER = 'event_id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recordIfNew(PaymentProviderEnum $provider, string $eventId): bool
    {
        try {
            $this->pdo->prepare(self::RECORD_QUERY)->execute([
                self::PROVIDER_PARAMETER => $provider->value,
                self::EVENT_ID_PARAMETER => $eventId,
            ]);
        } catch (PDOException $exception) {
            if (MysqlError::isDuplicateKey($exception)) {
                return false;
            }

            throw $exception;
        }

        return true;
    }
}
