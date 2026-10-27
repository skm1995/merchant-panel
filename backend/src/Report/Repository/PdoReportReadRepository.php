<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Order\Enum\OrderStatusEnum;
use App\Report\ValueObject\ReportPeriod;
use App\Report\ReadModel\DailySales;
use App\Report\ReadModel\ProductSales;
use App\Report\ReadModel\ReportedOrder;
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
        SELECT DATE(created_at) AS day, COUNT(*) AS orders, SUM(total_amount) AS revenue,
            :result_currency AS currency
        FROM orders
        WHERE status = :status
            AND created_at >= :starts_at
            AND created_at < :ends_before
            AND total_currency = :currency
        GROUP BY DATE(created_at)
        ORDER BY day
        SQL;
    public const string TOP_PRODUCTS_QUERY = <<<'SQL'
        SELECT order_items.product_id AS productId,
            MAX(order_items.sku) AS sku,
            MAX(order_items.name) AS name,
            SUM(order_items.quantity) AS unitsSold,
            SUM(order_items.quantity * order_items.unit_price_amount) AS revenue,
            :result_currency AS currency
        FROM orders
        JOIN order_items ON order_items.order_id = orders.id
        WHERE orders.status = :status
            AND orders.created_at >= :starts_at
            AND orders.created_at < :ends_before
            AND order_items.unit_price_currency = :currency
        GROUP BY order_items.product_id
        ORDER BY revenue DESC, productId
        LIMIT :limit
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

    public function dailySales(ReportPeriod $period): array
    {
        $statement = $this->pdo->prepare(self::DAILY_REVENUE_QUERY);
        $statement->execute(self::periodParameters($period));

        return array_map(
            static fn (DailySalesRecord $record): DailySales => $record->toEntity(),
            RecordFetcher::all($statement, DailySalesRecord::class),
        );
    }

    public function bestSellingProducts(ReportPeriod $period, int $limit): array
    {
        $statement = $this->pdo->prepare(self::TOP_PRODUCTS_QUERY);

        foreach (self::periodParameters($period) as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->bindValue(self::LIMIT_PARAMETER, $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (ProductSalesRecord $record): ProductSales => $record->toEntity(),
            RecordFetcher::all($statement, ProductSalesRecord::class),
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
