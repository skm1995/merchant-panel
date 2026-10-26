<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Payment\Entity\Payment;
use App\Payment\Entity\PaymentId;
use App\Payment\Entity\PaymentStart;
use App\Payment\Entity\PaymentState;
use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Enum\PaymentStatusEnum;
use App\Payment\Exception\PaymentIdempotencyKeyReusedException;
use App\Shared\Database\MysqlError;
use App\Shared\Database\RecordFetcher;
use PDO;
use PDOException;

final class PdoPaymentRepository implements PaymentRepositoryInterface
{
    private const string INSERT_QUERY = <<<'SQL'
        INSERT INTO payments (public_id, order_id, provider, provider_payment_id, checkout_url, amount, currency, status, idempotency_key)
        SELECT :public_id, orders.id, :provider, :provider_payment_id, :checkout_url, :amount, :currency, :status, :idempotency_key
        FROM orders
        WHERE orders.public_id = :order_public_id
        SQL;
    private const string FIND_START_QUERY = <<<'SQL'
        SELECT payments.public_id AS paymentId, orders.public_id AS orderId
        FROM payments
        JOIN orders ON orders.id = payments.order_id
        WHERE payments.idempotency_key = :idempotency_key
        SQL;
    private const string LOCK_QUERY = <<<'SQL'
        SELECT payments.public_id AS paymentId, orders.public_id AS orderId, payments.status, payments.amount, payments.currency
        FROM payments
        JOIN orders ON orders.id = payments.order_id
        WHERE payments.provider = :provider AND payments.provider_payment_id = :provider_payment_id
        FOR UPDATE OF payments
        SQL;
    private const string UPDATE_STATUS_QUERY = 'UPDATE payments SET status = :status, version = version + 1 WHERE public_id = :public_id';
    private const string PUBLIC_ID_PARAMETER = 'public_id';
    private const string ORDER_PUBLIC_ID_PARAMETER = 'order_public_id';
    private const string PROVIDER_PARAMETER = 'provider';
    private const string PROVIDER_PAYMENT_ID_PARAMETER = 'provider_payment_id';
    private const string CHECKOUT_URL_PARAMETER = 'checkout_url';
    private const string AMOUNT_PARAMETER = 'amount';
    private const string CURRENCY_PARAMETER = 'currency';
    private const string STATUS_PARAMETER = 'status';
    private const string IDEMPOTENCY_KEY_PARAMETER = 'idempotency_key';
    private const string IDEMPOTENCY_KEY_INDEX = 'payments_idempotency_key_unique';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(Payment $payment): void
    {
        try {
            $this->pdo->prepare(self::INSERT_QUERY)->execute([
                self::PUBLIC_ID_PARAMETER => $payment->id->toBinary(),
                self::ORDER_PUBLIC_ID_PARAMETER => $payment->orderId->toBinary(),
                self::PROVIDER_PARAMETER => $payment->provider->value,
                self::PROVIDER_PAYMENT_ID_PARAMETER => $payment->providerPaymentId,
                self::CHECKOUT_URL_PARAMETER => $payment->checkoutUrl,
                self::AMOUNT_PARAMETER => $payment->amount->getAmount(),
                self::CURRENCY_PARAMETER => $payment->amount->getCurrency()->getCode(),
                self::STATUS_PARAMETER => $payment->status->value,
                self::IDEMPOTENCY_KEY_PARAMETER => $payment->idempotencyKey,
            ]);
        } catch (PDOException $exception) {
            throw MysqlError::isDuplicateKey($exception, self::IDEMPOTENCY_KEY_INDEX) ? new PaymentIdempotencyKeyReusedException() : $exception;
        }
    }

    public function findStart(string $idempotencyKey): ?PaymentStart
    {
        $statement = $this->pdo->prepare(self::FIND_START_QUERY);
        $statement->execute([self::IDEMPOTENCY_KEY_PARAMETER => $idempotencyKey]);

        return RecordFetcher::one($statement, PaymentStartRecord::class)?->toEntity();
    }

    public function lockByProviderPaymentId(PaymentProviderEnum $provider, string $providerPaymentId): ?PaymentState
    {
        $statement = $this->pdo->prepare(self::LOCK_QUERY);
        $statement->execute([
            self::PROVIDER_PARAMETER => $provider->value,
            self::PROVIDER_PAYMENT_ID_PARAMETER => $providerPaymentId,
        ]);

        return RecordFetcher::one($statement, PaymentStateRecord::class)?->toEntity();
    }

    public function updateStatus(PaymentId $id, PaymentStatusEnum $status): void
    {
        $this->pdo->prepare(self::UPDATE_STATUS_QUERY)->execute([
            self::STATUS_PARAMETER => $status->value,
            self::PUBLIC_ID_PARAMETER => $id->toBinary(),
        ]);
    }

}
