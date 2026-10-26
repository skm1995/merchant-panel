<?php

declare(strict_types=1);

namespace App\Cart\Repository;

use App\Cart\Entity\Cart;
use App\Cart\Entity\CartId;
use App\Cart\Entity\CartLine;
use App\Cart\Enum\CartStatusEnum;
use App\Shared\Database\RecordFetcher;
use PDO;

final class PdoCartRepository implements CartRepositoryInterface
{
    private const string CREATE_QUERY = 'INSERT INTO carts (id, status) VALUES (:id, :status)';
    private const string FIND_QUERY = 'SELECT id, status FROM carts WHERE id = :id';
    private const string LOCK_QUERY = 'SELECT status FROM carts WHERE id = :id FOR UPDATE';
    private const string LINES_QUERY = <<<'SQL'
        SELECT cart_items.product_id AS productId, products.sku, products.name, cart_items.quantity,
            products.price_amount AS priceAmount, products.price_currency AS priceCurrency
        FROM cart_items
        JOIN products ON products.id = cart_items.product_id
        WHERE cart_items.cart_id = :id
        ORDER BY cart_items.product_id
        SQL;
    private const string SAVE_ITEM_QUERY = <<<'SQL'
        INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :quantity) AS new
        ON DUPLICATE KEY UPDATE quantity = new.quantity
        SQL;
    private const string UPDATE_STATUS_QUERY = 'UPDATE carts SET status = :status WHERE id = :id';
    private const string REMOVE_ITEM_QUERY = 'DELETE FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id';
    private const string ID_PARAMETER = 'id';
    private const string STATUS_PARAMETER = 'status';
    private const string CART_ID_PARAMETER = 'cart_id';
    private const string PRODUCT_ID_PARAMETER = 'product_id';
    private const string QUANTITY_PARAMETER = 'quantity';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(Cart $cart): void
    {
        $this->pdo->prepare(self::CREATE_QUERY)->execute([
            self::ID_PARAMETER => $cart->id->toBinary(),
            self::STATUS_PARAMETER => $cart->status->value,
        ]);
    }

    public function find(CartId $id): ?Cart
    {
        $statement = $this->pdo->prepare(self::FIND_QUERY);
        $statement->execute([self::ID_PARAMETER => $id->toBinary()]);
        $record = RecordFetcher::one($statement, CartRecord::class);

        if ($record === null) {
            return null;
        }

        $lines = $this->pdo->prepare(self::LINES_QUERY);
        $lines->execute([self::ID_PARAMETER => $id->toBinary()]);

        return new Cart(
            CartId::fromBinary($record->id),
            CartStatusEnum::from($record->status),
            array_map(
                static fn (CartLineRecord $line): CartLine => $line->toEntity(),
                RecordFetcher::all($lines, CartLineRecord::class),
            ),
        );
    }

    public function findStatusForUpdate(CartId $id): ?CartStatusEnum
    {
        $statement = $this->pdo->prepare(self::LOCK_QUERY);
        $statement->execute([self::ID_PARAMETER => $id->toBinary()]);
        $status = $statement->fetchColumn();

        return is_string($status) ? CartStatusEnum::from($status) : null;
    }

    public function updateStatus(CartId $id, CartStatusEnum $status): void
    {
        $this->pdo->prepare(self::UPDATE_STATUS_QUERY)->execute([
            self::ID_PARAMETER => $id->toBinary(),
            self::STATUS_PARAMETER => $status->value,
        ]);
    }

    public function saveItem(CartId $cartId, int $productId, int $quantity): void
    {
        $this->pdo->prepare(self::SAVE_ITEM_QUERY)->execute([
            self::CART_ID_PARAMETER => $cartId->toBinary(),
            self::PRODUCT_ID_PARAMETER => $productId,
            self::QUANTITY_PARAMETER => $quantity,
        ]);
    }

    public function removeItem(CartId $cartId, int $productId): void
    {
        $this->pdo->prepare(self::REMOVE_ITEM_QUERY)->execute([
            self::CART_ID_PARAMETER => $cartId->toBinary(),
            self::PRODUCT_ID_PARAMETER => $productId,
        ]);
    }
}
