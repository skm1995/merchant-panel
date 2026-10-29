<?php

declare(strict_types=1);

namespace App\Analytics\Service;

use App\Analytics\ValueObject\HistoryLine;
use App\Analytics\ValueObject\HistoryOrder;
use App\Analytics\ValueObject\HistoryProduct;
use App\Analytics\ValueObject\SalesDay;
use App\Order\Enum\OrderStatusEnum;
use App\Order\Enum\ReservedOrderKeyPrefixEnum;
use DateTimeImmutable;
use LogicException;
use Money\Money;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Makes up the products and orders of the sales history in PHP. Each day draws from a
 * random generator seeded with its date, so generating the same day again gives the same
 * orders.
 */
final readonly class SalesHistoryFactory
{
    public const string SKU_PREFIX = 'history-';
    public const string KEY_PREFIX = ReservedOrderKeyPrefixEnum::HISTORY->value;
    private const string SKU_FORMAT = self::SKU_PREFIX . '%04d';
    private const string NAME_FORMAT = '%s %s %d';
    private const string SEED_FORMAT = 'Ymd';
    private const string TIME_FORMAT = '+%d seconds';
    private const string NO_SHARES_MESSAGE = 'Cannot pick from empty shares.';
    private const int PRODUCT_SEED = 1;
    private const int MIN_PRICE = 990;
    private const int MAX_PRICE = 49990;
    // Popular products sell hundreds of units a day, so stock reaches into the thousands and
    // the stock outlook ranges from days to years.
    private const int MAX_STOCK = 20000;
    private const int MAX_LINES = 4;
    private const int SECONDS_PER_HOUR = 3600;
    private const int UUID_BYTES = 16;
    private const int VERSION_BYTE = 6;
    private const int VARIANT_BYTE = 8;

    /** @var list<string> */
    private const array ADJECTIVES = ['Wireless', 'Ergonomic', 'Compact', 'Premium', 'Smart', 'Portable', 'Classic', 'Pro'];

    /** @var list<string> */
    private const array NOUNS = ['Headphones', 'Keyboard', 'Mouse', 'Monitor', 'Speaker', 'Webcam', 'Charger', 'Lamp', 'Backpack', 'Router'];

    /**
     * Share of a day's orders in each hour, in percent: few at night, most in the evening.
     *
     * @var list<int>
     */
    private const array HOUR_SHARES = [1, 1, 1, 1, 1, 1, 2, 3, 4, 5, 5, 5, 6, 6, 5, 5, 5, 6, 7, 8, 8, 7, 5, 2];

    /** @var array<int, int> share of orders per quantity, in percent */
    private const array QUANTITY_SHARES = [1 => 70, 2 => 20, 3 => 10];

    /** @var array<string, int> share of orders per status, in percent */
    private const array STATUS_SHARES = ['paid' => 92, 'cancelled' => 5, 'expired' => 3];

    /** @return list<HistoryProduct> */
    public function products(int $count): array
    {
        $randomizer = new Randomizer(new Mt19937(self::PRODUCT_SEED));
        $products = [];

        for ($number = 1; $number <= $count; $number++) {
            $products[] = new HistoryProduct(
                sprintf(self::SKU_FORMAT, $number),
                sprintf(
                    self::NAME_FORMAT,
                    self::ADJECTIVES[$randomizer->getInt(0, count(self::ADJECTIVES) - 1)],
                    self::NOUNS[$randomizer->getInt(0, count(self::NOUNS) - 1)],
                    $number,
                ),
                Money::PLN($randomizer->getInt(self::MIN_PRICE, self::MAX_PRICE)),
                $randomizer->getInt(0, self::MAX_STOCK),
            );
        }

        return $products;
    }

    /**
     * @param array<int, HistoryProduct> $products the history products keyed by their id
     * @return list<HistoryOrder> the day's orders, numbered from $firstNumber on
     */
    public function ordersForDay(SalesDay $day, int $firstNumber, array $products): array
    {
        $randomizer = new Randomizer(new Mt19937((int) $day->date->format(self::SEED_FORMAT)));
        $productIds = array_keys($products);
        $orders = [];

        for ($number = $firstNumber; $number < $firstNumber + $day->orders; $number++) {
            $orders[] = new HistoryOrder(
                self::uuid($randomizer),
                self::uuid($randomizer),
                self::KEY_PREFIX . $number,
                OrderStatusEnum::from(self::pick($randomizer, self::STATUS_SHARES)),
                self::placedAt($randomizer, $day->date),
                self::lines($randomizer, $productIds, $products),
            );
        }

        return $orders;
    }

    private static function placedAt(Randomizer $randomizer, DateTimeImmutable $day): DateTimeImmutable
    {
        $hour = self::pick($randomizer, self::HOUR_SHARES);
        $seconds = $hour * self::SECONDS_PER_HOUR + $randomizer->getInt(0, self::SECONDS_PER_HOUR - 1);

        return $day->setTime(0, 0)->modify(sprintf(self::TIME_FORMAT, $seconds));
    }

    /**
     * The first product is drawn with a squared random number, so the first products in the
     * list sell far more than the rest; the others are drawn evenly and never repeat.
     *
     * @param list<int> $productIds
     * @param array<int, HistoryProduct> $products
     * @return non-empty-list<HistoryLine>
     */
    private static function lines(Randomizer $randomizer, array $productIds, array $products): array
    {
        $lineCount = min($randomizer->getInt(1, self::MAX_LINES), count($productIds));
        $chosen = [$productIds[(int) floor(count($productIds) * $randomizer->nextFloat() ** 2)] => true];

        while (count($chosen) < $lineCount) {
            $chosen[$productIds[$randomizer->getInt(0, count($productIds) - 1)]] = true;
        }

        $lines = [];
        foreach (array_keys($chosen) as $productId) {
            $product = $products[$productId];
            $lines[] = new HistoryLine($productId, $product->sku, $product->name, self::pick($randomizer, self::QUANTITY_SHARES), $product->price);
        }

        return $lines;
    }

    /**
     * Draws a key of the shares array with a chance proportional to its share.
     *
     * @template TKey of array-key
     * @param array<TKey, int> $shares
     * @return TKey
     */
    private static function pick(Randomizer $randomizer, array $shares): int|string
    {
        $roll = $randomizer->getInt(1, array_sum($shares));

        foreach ($shares as $key => $share) {
            $roll -= $share;
            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($shares) ?? throw new LogicException(self::NO_SHARES_MESSAGE);
    }

    /**
     * 16 random bytes with the version 4 and variant bits set, as the application's UUIDs.
     */
    private static function uuid(Randomizer $randomizer): string
    {
        $bytes = $randomizer->getBytes(self::UUID_BYTES);
        $bytes[self::VERSION_BYTE] = chr(ord($bytes[self::VERSION_BYTE]) & 0x0f | 0x40);
        $bytes[self::VARIANT_BYTE] = chr(ord($bytes[self::VARIANT_BYTE]) & 0x3f | 0x80);

        return $bytes;
    }
}
