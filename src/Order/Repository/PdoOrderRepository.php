<?php

declare(strict_types=1);

namespace App\Order\Repository;

use App\Order\Entity\Order;
use App\Order\Entity\OrderId;
use App\Order\Entity\OrderPlacement;
use App\Order\Entity\OrderState;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Exception\IdempotencyKeyReusedException;
use App\Shared\Database\RecordFetcher;
use PDO;
use PDOException;

final class PdoOrderRepository implements OrderRepositoryInterface
{
    private const string INSERT_ORDER_QUERY = <<<'SQL'
        INSERT INTO orders (public_id, cart_id, idempotency_key, status, total_amount, total_currency, reservation_expires_at)
        VALUES (:public_id, :cart_id, :idempotency_key, :status, :total_amount, :total_currency, :reservation_expires_at)
        SQL;
    private const string INSERT_LINE_QUERY = <<<'SQL'
        INSERT INTO order_items (order_id, product_id, sku, name, quantity, unit_price_amount, unit_price_currency)
        VALUES (:order_id, :product_id, :sku, :name, :quantity, :unit_price_amount, :unit_price_currency)
        SQL;
    private const string FIND_PLACEMENT_QUERY = 'SELECT public_id AS publicId, cart_id AS cartId FROM orders WHERE idempotency_key = :idempotency_key';
    private const string FIND_STATE_QUERY = 'SELECT id AS internalId, public_id AS publicId, status, version FROM orders WHERE public_id = :public_id';
    private const string QUANTITIES_QUERY = 'SELECT product_id, quantity FROM order_items WHERE order_id = :order_id ORDER BY product_id';
    private const string TRANSITION_QUERY = <<<'SQL'
        UPDATE orders
        SET status = :to_status, version = version + 1
        WHERE public_id = :public_id AND status = :from_status AND version = :expected_version
        SQL;
    private const string TO_STATUS_PARAMETER = 'to_status';
    private const string FROM_STATUS_PARAMETER = 'from_status';
    private const string EXPECTED_VERSION_PARAMETER = 'expected_version';
    private const string IDEMPOTENCY_KEY_INDEX = 'orders_idempotency_key_unique';
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const string PUBLIC_ID_PARAMETER = 'public_id';
    private const string CART_ID_PARAMETER = 'cart_id';
    private const string IDEMPOTENCY_KEY_PARAMETER = 'idempotency_key';
    private const string STATUS_PARAMETER = 'status';
    private const string TOTAL_AMOUNT_PARAMETER = 'total_amount';
    private const string TOTAL_CURRENCY_PARAMETER = 'total_currency';
    private const string RESERVATION_EXPIRES_AT_PARAMETER = 'reservation_expires_at';
    private const string ORDER_ID_PARAMETER = 'order_id';
    private const string PRODUCT_ID_PARAMETER = 'product_id';
    private const string SKU_PARAMETER = 'sku';
    private const string NAME_PARAMETER = 'name';
    private const string QUANTITY_PARAMETER = 'quantity';
    private const string UNIT_PRICE_AMOUNT_PARAMETER = 'unit_price_amount';
    private const string UNIT_PRICE_CURRENCY_PARAMETER = 'unit_price_currency';
    private const int DRIVER_ERROR_CODE_INDEX = 1;
    private const int DRIVER_MESSAGE_INDEX = 2;
    private const int DUPLICATE_KEY_ERROR_CODE = 1062;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(Order $order): void
    {
        $total = $order->total();

        try {
            $this->pdo->prepare(self::INSERT_ORDER_QUERY)->execute([
                self::PUBLIC_ID_PARAMETER => $order->id->toBinary(),
                self::CART_ID_PARAMETER => $order->cartId->toBinary(),
                self::IDEMPOTENCY_KEY_PARAMETER => $order->idempotencyKey,
                self::STATUS_PARAMETER => $order->status->value,
                self::TOTAL_AMOUNT_PARAMETER => $total->getAmount(),
                self::TOTAL_CURRENCY_PARAMETER => $total->getCurrency()->getCode(),
                self::RESERVATION_EXPIRES_AT_PARAMETER => $order->reservationExpiresAt->format(self::DATETIME_FORMAT),
            ]);
        } catch (PDOException $exception) {
            throw self::isIdempotencyKeyConflict($exception) ? new IdempotencyKeyReusedException($exception) : $exception;
        }

        $orderId = (int) $this->pdo->lastInsertId();
        $insertLine = $this->pdo->prepare(self::INSERT_LINE_QUERY);

        foreach ($order->lines as $line) {
            $insertLine->execute([
                self::ORDER_ID_PARAMETER => $orderId,
                self::PRODUCT_ID_PARAMETER => $line->productId,
                self::SKU_PARAMETER => $line->sku,
                self::NAME_PARAMETER => $line->name,
                self::QUANTITY_PARAMETER => $line->quantity,
                self::UNIT_PRICE_AMOUNT_PARAMETER => $line->unitPrice->getAmount(),
                self::UNIT_PRICE_CURRENCY_PARAMETER => $line->unitPrice->getCurrency()->getCode(),
            ]);
        }
    }

    public function findPlacement(string $idempotencyKey): ?OrderPlacement
    {
        $statement = $this->pdo->prepare(self::FIND_PLACEMENT_QUERY);
        $statement->execute([self::IDEMPOTENCY_KEY_PARAMETER => $idempotencyKey]);

        return RecordFetcher::one($statement, OrderPlacementRecord::class)?->toEntity();
    }

    public function findState(OrderId $id): ?OrderState
    {
        $statement = $this->pdo->prepare(self::FIND_STATE_QUERY);
        $statement->execute([self::PUBLIC_ID_PARAMETER => $id->toBinary()]);
        $record = RecordFetcher::one($statement, OrderStateRecord::class);

        if ($record === null) {
            return null;
        }

        $quantities = $this->pdo->prepare(self::QUANTITIES_QUERY);
        $quantities->execute([self::ORDER_ID_PARAMETER => $record->internalId]);

        /** @var array<int, int> $quantitiesByProduct */
        $quantitiesByProduct = $quantities->fetchAll(PDO::FETCH_KEY_PAIR);

        return new OrderState(
            OrderId::fromBinary($record->publicId),
            OrderStatusEnum::from($record->status),
            $record->version,
            $quantitiesByProduct,
        );
    }

    public function transition(OrderId $id, OrderStatusEnum $from, OrderStatusEnum $to, int $expectedVersion): bool
    {
        $statement = $this->pdo->prepare(self::TRANSITION_QUERY);
        $statement->execute([
            self::PUBLIC_ID_PARAMETER => $id->toBinary(),
            self::FROM_STATUS_PARAMETER => $from->value,
            self::TO_STATUS_PARAMETER => $to->value,
            self::EXPECTED_VERSION_PARAMETER => $expectedVersion,
        ]);

        return $statement->rowCount() === 1;
    }

    private static function isIdempotencyKeyConflict(PDOException $exception): bool
    {
        return ($exception->errorInfo[self::DRIVER_ERROR_CODE_INDEX] ?? null) === self::DUPLICATE_KEY_ERROR_CODE
            && str_contains((string) ($exception->errorInfo[self::DRIVER_MESSAGE_INDEX] ?? null), self::IDEMPOTENCY_KEY_INDEX);
    }
}
