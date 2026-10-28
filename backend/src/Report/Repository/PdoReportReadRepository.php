<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Order\Enum\OrderStatusEnum;
use App\Report\ValueObject\ReportPeriod;
use App\Report\ReadModel\DailySales;
use App\Report\ReadModel\HeatmapCell;
use App\Report\ReadModel\MonthRevenue;
use App\Report\ReadModel\ProductPace;
use App\Report\ReadModel\ProductSales;
use App\Report\ReadModel\ReportedOrder;
use App\Shared\Database\RecordFetcher;
use App\Shared\Database\SqlDateTime;
use DateTimeImmutable;
use Money\Currency;
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
    public const string STOCK_OUTLOOK_QUERY = <<<'SQL'
        SELECT products.id AS productId, products.sku, products.name,
            products.stock - products.reserved AS available,
            SUM(daily_product_sales.units) AS unitsSold
        FROM daily_product_sales
        JOIN products ON products.id = daily_product_sales.product_id
        WHERE daily_product_sales.day >= :starts_at AND daily_product_sales.day < :ends_before
        GROUP BY products.id, products.sku, products.name, products.stock, products.reserved
        ORDER BY (products.stock - products.reserved) / SUM(daily_product_sales.units), products.id
        LIMIT :limit
        SQL;
    public const string ORDER_HEATMAP_QUERY = <<<'SQL'
        SELECT WEEKDAY(created_at) + 1 AS weekday, HOUR(created_at) AS hour, COUNT(*) AS orders
        FROM orders
        WHERE status = :status
            AND created_at >= :starts_at
            AND created_at < :ends_before
        GROUP BY weekday, hour
        ORDER BY weekday, hour
        SQL;
    public const string MONTHLY_REVENUE_QUERY = <<<'SQL'
        SELECT DATE_FORMAT(day, '%Y-%m') AS month, SUM(revenue) AS revenue, :result_currency AS currency
        FROM daily_product_sales
        WHERE currency = :currency AND day >= :starts_at AND day < :ends_before
        GROUP BY month
        ORDER BY month
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
    private const string DATE_FORMAT = 'Y-m-d';

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

    public function productSalesBetween(DateTimeImmutable $startsAt, DateTimeImmutable $endsBefore, int $limit): array
    {
        $statement = $this->pdo->prepare(self::STOCK_OUTLOOK_QUERY);
        $statement->bindValue(self::STARTS_AT_PARAMETER, $startsAt->format(self::DATE_FORMAT));
        $statement->bindValue(self::ENDS_BEFORE_PARAMETER, $endsBefore->format(self::DATE_FORMAT));
        $statement->bindValue(self::LIMIT_PARAMETER, $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (ProductPaceRecord $record): ProductPace => $record->toEntity(),
            RecordFetcher::all($statement, ProductPaceRecord::class),
        );
    }

    public function paidOrdersByWeekdayAndHour(ReportPeriod $period): array
    {
        $statement = $this->pdo->prepare(self::ORDER_HEATMAP_QUERY);
        $statement->execute(self::orderRangeParameters($period));

        return array_map(
            static fn (HeatmapCellRecord $record): HeatmapCell => $record->toEntity(),
            RecordFetcher::all($statement, HeatmapCellRecord::class),
        );
    }

    public function revenueByMonth(Currency $currency, DateTimeImmutable $startsAt, DateTimeImmutable $endsBefore): array
    {
        $statement = $this->pdo->prepare(self::MONTHLY_REVENUE_QUERY);
        $statement->execute(self::monthlyRevenueParameters($currency, $startsAt, $endsBefore));

        return array_map(
            static fn (MonthRevenueRecord $record): MonthRevenue => $record->toEntity(),
            RecordFetcher::all($statement, MonthRevenueRecord::class),
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
    public static function orderRangeParameters(ReportPeriod $period): array
    {
        return [
            self::STATUS_PARAMETER => OrderStatusEnum::PAID->value,
            self::STARTS_AT_PARAMETER => SqlDateTime::format($period->startsAt()),
            self::ENDS_BEFORE_PARAMETER => SqlDateTime::format($period->endsBefore()),
        ];
    }

    /** @return array<string, string> */
    public static function monthlyRevenueParameters(Currency $currency, DateTimeImmutable $startsAt, DateTimeImmutable $endsBefore): array
    {
        return [
            self::CURRENCY_PARAMETER => $currency->getCode(),
            self::RESULT_CURRENCY_PARAMETER => $currency->getCode(),
            self::STARTS_AT_PARAMETER => $startsAt->format(self::DATE_FORMAT),
            self::ENDS_BEFORE_PARAMETER => $endsBefore->format(self::DATE_FORMAT),
        ];
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
