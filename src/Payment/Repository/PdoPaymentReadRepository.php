<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Payment\Entity\PaymentId;
use App\Payment\ReadModel\PaymentView;
use App\Shared\Database\RecordFetcher;
use PDO;

final class PdoPaymentReadRepository implements PaymentReadRepositoryInterface
{
    private const string FIND_QUERY = <<<'SQL'
        SELECT payments.public_id AS paymentId, orders.public_id AS orderId, payments.provider, payments.status,
            payments.amount, payments.currency, payments.checkout_url AS checkoutUrl
        FROM payments
        JOIN orders ON orders.id = payments.order_id
        WHERE payments.public_id = :public_id
        SQL;
    private const string PUBLIC_ID_PARAMETER = 'public_id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(PaymentId $id): ?PaymentView
    {
        $statement = $this->pdo->prepare(self::FIND_QUERY);
        $statement->execute([self::PUBLIC_ID_PARAMETER => $id->toBinary()]);

        return RecordFetcher::one($statement, PaymentViewRecord::class)?->toEntity();
    }
}
