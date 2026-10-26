<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Order\Entity\OrderId;
use App\Order\Enum\OrderStatusEnum;
use App\Order\ReadModel\OrderLineView;
use App\Order\ReadModel\OrderView;
use App\Shared\Database\RecordFetcher;
use DateTimeImmutable;
use Money\Currency;
use Money\Money;
use PDO;

final class PdoOrderReadRepository implements OrderReadRepositoryInterface
{
    private const string ORDER_QUERY = <<<'SQL'
        SELECT id AS internalId, public_id AS publicId, status, total_amount AS totalAmount,
            total_currency AS totalCurrency, reservation_expires_at AS reservationExpiresAt, created_at AS createdAt, version
        FROM orders
        WHERE public_id = :public_id
        SQL;
    private const string LINES_QUERY = <<<'SQL'
        SELECT product_id AS productId, sku, name, quantity,
            unit_price_amount AS unitPriceAmount, unit_price_currency AS unitPriceCurrency
        FROM order_items
        WHERE order_id = :order_id
        ORDER BY product_id
        SQL;
    private const string PUBLIC_ID_PARAMETER = 'public_id';
    private const string ORDER_ID_PARAMETER = 'order_id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(OrderId $id): ?OrderView
    {
        $orderStatement = $this->pdo->prepare(self::ORDER_QUERY);
        $orderStatement->execute([self::PUBLIC_ID_PARAMETER => $id->toBinary()]);
        $order = RecordFetcher::one($orderStatement, OrderViewRecord::class);

        if ($order === null) {
            return null;
        }

        $linesStatement = $this->pdo->prepare(self::LINES_QUERY);
        $linesStatement->execute([self::ORDER_ID_PARAMETER => $order->internalId]);

        return new OrderView(
            OrderId::fromBinary($order->publicId),
            OrderStatusEnum::from($order->status),
            array_map(
                static fn (OrderLineViewRecord $line): OrderLineView => $line->toEntity(),
                RecordFetcher::all($linesStatement, OrderLineViewRecord::class),
            ),
            new Money($order->totalAmount, new Currency($order->totalCurrency)),
            new DateTimeImmutable($order->reservationExpiresAt),
            new DateTimeImmutable($order->createdAt),
            $order->version,
        );
    }
}
