<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Order\Entity\OrderId;
use App\Order\Enum\OrderStatusEnum;
use App\Order\ReadModel\OrderLineView;
use App\Order\ReadModel\OrderSummary;
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
    private const string PAGE_COLUMNS = 'public_id AS publicId, status, total_amount AS totalAmount, total_currency AS totalCurrency, created_at AS createdAt';
    private const string FIRST_PAGE_QUERY = 'SELECT ' . self::PAGE_COLUMNS . ' FROM orders ORDER BY created_at DESC, id DESC LIMIT :limit';
    private const string CURSOR_QUERY = 'SELECT created_at, id FROM orders WHERE public_id = :public_id';
    /**
     * Keyset pagination on the orders_created_at index, which InnoDB extends with the
     * primary key, so the range condition reads only the rows of the requested page.
     */
    public const string NEXT_PAGE_QUERY = <<<'SQL'
        SELECT public_id AS publicId, status, total_amount AS totalAmount, total_currency AS totalCurrency, created_at AS createdAt
        FROM orders
        WHERE created_at < :created_at OR (created_at = :same_created_at AND id < :id)
        ORDER BY created_at DESC, id DESC
        LIMIT :limit
        SQL;
    private const string LIMIT_PARAMETER = 'limit';
    private const string CREATED_AT_PARAMETER = 'created_at';
    private const string SAME_CREATED_AT_PARAMETER = 'same_created_at';
    private const string ID_PARAMETER = 'id';
    private const string CURSOR_CREATED_AT_COLUMN = 'created_at';
    private const string CURSOR_ID_COLUMN = 'id';
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

    public function findPage(int $limit, ?OrderId $after): array
    {
        if ($after === null) {
            $statement = $this->pdo->prepare(self::FIRST_PAGE_QUERY);
        } else {
            $cursor = $this->pdo->prepare(self::CURSOR_QUERY);
            $cursor->execute([self::PUBLIC_ID_PARAMETER => $after->toBinary()]);
            $position = $cursor->fetch(PDO::FETCH_ASSOC);

            if (!is_array($position)) {
                return [];
            }

            $statement = $this->pdo->prepare(self::NEXT_PAGE_QUERY);
            $statement->bindValue(self::CREATED_AT_PARAMETER, $position[self::CURSOR_CREATED_AT_COLUMN]);
            $statement->bindValue(self::SAME_CREATED_AT_PARAMETER, $position[self::CURSOR_CREATED_AT_COLUMN]);
            $statement->bindValue(self::ID_PARAMETER, $position[self::CURSOR_ID_COLUMN], PDO::PARAM_INT);
        }

        $statement->bindValue(self::LIMIT_PARAMETER, $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (OrderSummaryRecord $record): OrderSummary => $record->toEntity(),
            RecordFetcher::all($statement, OrderSummaryRecord::class),
        );
    }
}
