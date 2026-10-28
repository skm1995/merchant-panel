<?php

declare(strict_types=1);

namespace App\Analytics\Repository;

use App\Order\Entity\OrderId;
use App\Order\Enum\OrderStatusEnum;
use DateTimeImmutable;
use PDO;

/**
 * Rebuilds a day of the summary by deleting it and inserting the day's paid sales grouped by
 * product. Both statements read one range: the summary's key starts with the day, and the
 * orders are found through orders_status_created_at.
 */
final class PdoDailySalesRepository implements DailySalesRepositoryInterface
{
    private const string DELETE_DAY_QUERY = 'DELETE FROM daily_product_sales WHERE day = :day';
    private const string INSERT_DAY_QUERY = <<<'SQL'
        INSERT INTO daily_product_sales (day, product_id, currency, units, revenue)
        SELECT :day, order_items.product_id, order_items.unit_price_currency,
            SUM(order_items.quantity), SUM(order_items.quantity * order_items.unit_price_amount)
        FROM orders
        JOIN order_items ON order_items.order_id = orders.id
        WHERE orders.status = :status
            AND orders.created_at >= :starts_at
            AND orders.created_at < :ends_before
        GROUP BY order_items.product_id, order_items.unit_price_currency
        SQL;
    private const string PLACEMENT_DAY_QUERY = 'SELECT DATE(created_at) FROM orders WHERE public_id = :public_id';
    private const string DATE_FORMAT = 'Y-m-d';
    private const string NEXT_DAY = '+1 day';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function refreshDay(DateTimeImmutable $day): void
    {
        $date = $day->format(self::DATE_FORMAT);

        $this->pdo->prepare(self::DELETE_DAY_QUERY)->execute(['day' => $date]);
        $this->pdo->prepare(self::INSERT_DAY_QUERY)->execute([
            'day' => $date,
            'status' => OrderStatusEnum::PAID->value,
            'starts_at' => $date,
            'ends_before' => $day->modify(self::NEXT_DAY)->format(self::DATE_FORMAT),
        ]);
    }

    public function findPlacementDay(OrderId $orderId): ?DateTimeImmutable
    {
        $statement = $this->pdo->prepare(self::PLACEMENT_DAY_QUERY);
        $statement->execute(['public_id' => $orderId->toBinary()]);
        $day = $statement->fetchColumn();

        return is_string($day) ? new DateTimeImmutable($day) : null;
    }
}
