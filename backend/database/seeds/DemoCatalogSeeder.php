<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Products for the public demo, so a fresh deployment has a catalog to browse and edit.
 * Running it again adds only missing products and leaves edited ones as they are.
 */
final class DemoCatalogSeeder extends AbstractSeed
{
    private const string INSERT_QUERY = 'INSERT INTO products (sku, name, price_amount, price_currency, stock) VALUES (:sku, :name, :price_amount, :price_currency, :stock) ON DUPLICATE KEY UPDATE id = id';
    private const string CURRENCY = 'PLN';

    /** @var list<array{string, string, int, int}> SKU, name, price in grosze, stock */
    private const array PRODUCTS = [
        ['demo-keyboard', 'Mechanical keyboard', 34900, 40],
        ['demo-mouse', 'Wireless mouse', 12900, 120],
        ['demo-monitor', '27" 4K monitor', 179900, 15],
        ['demo-headphones', 'Noise-cancelling headphones', 89900, 30],
        ['demo-webcam', 'Full HD webcam', 24900, 60],
        ['demo-dock', 'USB-C docking station', 49900, 25],
        ['demo-ssd', '2 TB NVMe SSD', 64900, 50],
        ['demo-chair', 'Ergonomic office chair', 129900, 8],
        ['demo-lamp', 'LED desk lamp', 15900, 75],
        ['demo-backpack', 'Laptop backpack', 19900, 0],
    ];

    public function run(): void
    {
        $statement = $this->getAdapter()->getConnection()->prepare(self::INSERT_QUERY);

        foreach (self::PRODUCTS as [$sku, $name, $priceInMinorUnits, $stock]) {
            $statement->execute([
                'sku' => $sku,
                'name' => $name,
                'price_amount' => $priceInMinorUnits,
                'price_currency' => self::CURRENCY,
                'stock' => $stock,
            ]);
        }
    }
}
