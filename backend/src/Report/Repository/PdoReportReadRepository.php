<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Order\Enum\OrderStatusEnum;
use App\Report\ValueObject\ReportPeriod;
use App\Report\ReadModel\DailyRevenue;
use App\Report\ReadModel\ReportedOrder;
use App\Report\ReadModel\TopProduct;
use App\Shared\Database\RecordFetcher;
use App\Shared\Database\SqlDateTime;
use DateTimeImmutable;
use PDO;

/**
 * Hand-written reporting SQL over paid orders. Both queries filter orders by status and a
 * half-open created_at range so MySQL can range-scan orders_status_created_at.
 */
final class PdoReportReadRepository implements ReportReadRepositoryInterface
{
    public const string DAILY_REVENUE_QUERY = <<<'SQL'
        WITH daily AS (
            SELECT DATE(created_at) AS day, COUNT(*) AS orders, SUM(total_amount) AS revenue
            FROM orders
            WHERE status = :status
                AND created_at >= :starts_at
                AND created_at < :ends_before
                AND total_currency = :currency
            GROUP BY DATE(created_at)
        )
        SELECT day, orders, revenue,
            SUM(revenue) OVER (ORDER BY day ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS runningTotal,
            :result_currency AS currency
        FROM daily
        ORDER BY day
        SQL;
    public const string TOP_PRODUCTS_QUERY = <<<'SQL'
        WITH sales AS (
            SELECT order_items.product_id,
                MAX(order_items.sku) AS sku,
                MAX(order_items.name) AS name,
                SUM(order_items.quantity) AS units_sold,
                SUM(order_items.quantity * order_items.unit_price_amount) AS revenue
            FROM orders
            JOIN order_items ON order_items.order_id = orders.id
            WHERE orders.status = :status
                AND orders.created_at >= :starts_at
                AND orders.created_at < :ends_before
                AND order_items.unit_price_currency = :currency
            GROUP BY order_items.product_id
        ),
        ranked AS (
            SELECT sales.*, DENSE_RANK() OVER (ORDER BY revenue DESC) AS revenue_rank
            FROM sales
        )
        SELECT revenue_rank AS revenueRank, product_id AS productId, sku, name,
            units_sold AS unitsSold, revenue, :result_currency AS currency
        FROM ranked
        WHERE revenue_rank <= :limit
        ORDER BY revenue_rank, product_id
        SQL;
    private const string ORDERS_BY_STATUS_QUERY = <<<'SQL'
        SELECT orders.id AS orderNumber,
            GROUP_CONCAT(order_items.name ORDER BY order_items.name SEPARATOR ', ') AS products,
            orders.total_amount AS totalAmount,
            orders.total_currency AS totalCurrency,
            orders.created_at AS placedAt
        FROM orders
        LEFT JOIN order_items ON order_items.order_id = orders.id
        WHERE orders.status = :status AND orders.created_at >= :since
        GROUP BY orders.id
        ORDER BY orders.created_at DESC, orders.id DESC
        LIMIT :limit
        SQL;
    private const string SINCE_PARAMETER = 'since';
    private const string STATUS_PARAMETER = 'status';
    private const string STARTS_AT_PARAMETER = 'starts_at';
    private const string ENDS_BEFORE_PARAMETER = 'ends_before';
    private const string CURRENCY_PARAMETER = 'currency';
    private const string RESULT_CURRENCY_PARAMETER = 'result_currency';
    private const string LIMIT_PARAMETER = 'limit';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function dailyRevenue(ReportPeriod $period): array
    {
        $statement = $this->pdo->prepare(self::DAILY_REVENUE_QUERY);
        $statement->execute(self::periodParameters($period));

        return array_map(
            static fn (DailyRevenueRecord $record): DailyRevenue => $record->toEntity(),
            RecordFetcher::all($statement, DailyRevenueRecord::class),
        );
    }

    public function topProducts(ReportPeriod $period, int $limit): array
    {
        $statement = $this->pdo->prepare(self::TOP_PRODUCTS_QUERY);

        foreach (self::periodParameters($period) as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->bindValue(self::LIMIT_PARAMETER, $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (TopProductRecord $record): TopProduct => $record->toEntity(),
            RecordFetcher::all($statement, TopProductRecord::class),
        );
    }

    public function ordersByStatus(OrderStatusEnum $status, DateTimeImmutable $since, int $limit): array
    {
        $statement = $this->pdo->prepare(self::ORDERS_BY_STATUS_QUERY);
        $statement->bindValue(self::STATUS_PARAMETER, $status->value);
        $statement->bindValue(self::SINCE_PARAMETER, SqlDateTime::format($since));
        $statement->bindValue(self::LIMIT_PARAMETER, $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (ReportedOrderRecord $record): ReportedOrder => $record->toEntity(),
            RecordFetcher::all($statement, ReportedOrderRecord::class),
        );
    }

    /** @return array<string, string> */
    public static function periodParameters(ReportPeriod $period): array
    {
        return [
            self::STATUS_PARAMETER => OrderStatusEnum::PAID->value,
            self::STARTS_AT_PARAMETER => SqlDateTime::format($period->startsAt()),
            self::ENDS_BEFORE_PARAMETER => SqlDateTime::format($period->endsBefore()),
            self::CURRENCY_PARAMETER => $period->currency->getCode(),
            self::RESULT_CURRENCY_PARAMETER => $period->currency->getCode(),
        ];
    }
}
