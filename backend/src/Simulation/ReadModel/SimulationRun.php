<?php

declare(strict_types=1);

namespace App\Simulation\ReadModel;

use DateTimeImmutable;

/**
 * A simulation as it is stored while it runs and after it: when it runs, how many buyers
 * shop, the stock its products started with, and, once finished, its check.
 */
final readonly class SimulationRun
{
    private const string ID_KEY = 'id';
    private const string STARTED_AT_KEY = 'started_at';
    private const string ENDS_AT_KEY = 'ends_at';
    private const string BUYERS_KEY = 'buyers';
    private const string PRODUCTS_KEY = 'products';
    private const string CHECK_KEY = 'check';
    private const string PRODUCT_ID_KEY = 'product_id';
    private const string SKU_KEY = 'sku';
    private const string NAME_KEY = 'name';
    private const string STOCK_KEY = 'stock';
    private const string RESERVED_KEY = 'reserved';
    private const string UNITS_SOLD_KEY = 'units_sold';
    private const string UNITS_PAID_KEY = 'units_paid';
    private const string DATE_FORMAT = DATE_ATOM;

    /** @param list<SimulationProduct> $productsAtStart */
    public function __construct(
        public string $id,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $endsAt,
        public int $buyers,
        public array $productsAtStart,
        public ?SimulationCheck $check = null,
    ) {
    }

    public function isFinished(): bool
    {
        return $this->check !== null;
    }

    public function finish(SimulationCheck $check): self
    {
        return new self($this->id, $this->startedAt, $this->endsAt, $this->buyers, $this->productsAtStart, $check);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            self::ID_KEY => $this->id,
            self::STARTED_AT_KEY => $this->startedAt->format(self::DATE_FORMAT),
            self::ENDS_AT_KEY => $this->endsAt->format(self::DATE_FORMAT),
            self::BUYERS_KEY => $this->buyers,
            self::PRODUCTS_KEY => array_map(static fn (SimulationProduct $product): array => [
                self::PRODUCT_ID_KEY => $product->productId,
                self::SKU_KEY => $product->sku,
                self::NAME_KEY => $product->name,
                self::STOCK_KEY => $product->stock,
                self::RESERVED_KEY => $product->reserved,
            ], $this->productsAtStart),
            self::CHECK_KEY => $this->check === null ? null : [
                self::UNITS_SOLD_KEY => $this->check->unitsSold,
                self::UNITS_PAID_KEY => $this->check->unitsPaid,
            ],
        ];
    }

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, int|string>> $products */
        $products = $data[self::PRODUCTS_KEY] ?? [];
        /** @var array<string, int>|null $check */
        $check = $data[self::CHECK_KEY] ?? null;

        return new self(
            (string) $data[self::ID_KEY],
            new DateTimeImmutable((string) $data[self::STARTED_AT_KEY]),
            new DateTimeImmutable((string) $data[self::ENDS_AT_KEY]),
            (int) $data[self::BUYERS_KEY],
            array_map(static fn (array $product): SimulationProduct => new SimulationProduct(
                (int) $product[self::PRODUCT_ID_KEY],
                (string) $product[self::SKU_KEY],
                (string) $product[self::NAME_KEY],
                (int) $product[self::STOCK_KEY],
                (int) $product[self::RESERVED_KEY],
            ), $products),
            $check === null ? null : new SimulationCheck((int) $check[self::UNITS_SOLD_KEY], (int) $check[self::UNITS_PAID_KEY]),
        );
    }
}
