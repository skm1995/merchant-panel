<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Payment\Enum\PaymentProviderEnum;
use PDO;
use PDOException;

final class PdoWebhookInboxRepository implements WebhookInboxRepositoryInterface
{
    private const string RECORD_QUERY = 'INSERT INTO payment_webhook_events (provider, event_id) VALUES (:provider, :event_id)';
    private const string PROVIDER_PARAMETER = 'provider';
    private const string EVENT_ID_PARAMETER = 'event_id';
    private const int DRIVER_ERROR_CODE_INDEX = 1;
    private const int DUPLICATE_KEY_ERROR_CODE = 1062;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(PaymentProviderEnum $provider, string $eventId): bool
    {
        try {
            $this->pdo->prepare(self::RECORD_QUERY)->execute([
                self::PROVIDER_PARAMETER => $provider->value,
                self::EVENT_ID_PARAMETER => $eventId,
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[self::DRIVER_ERROR_CODE_INDEX] ?? null) === self::DUPLICATE_KEY_ERROR_CODE) {
                return false;
            }

            throw $exception;
        }

        return true;
    }
}
