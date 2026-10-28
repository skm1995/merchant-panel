<?php

declare(strict_types=1);

use App\Analytics\Exception\SalesHistoryAlreadyExists;
use App\Analytics\Service\GenerateSalesHistoryService;
use App\Shared\DependencyInjection\ContainerFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFAULT_ORDERS = 3000000;
const PROGRESS_FORMAT = "\rGenerated %d of %d orders (%d%%) in %ds.";
const SUMMARY_FORMAT = '%sDone.%s';
const ERROR_FORMAT = '%s%s';
const USAGE_FORMAT = 'Usage: php bin/generate-sales-history.php [orders, default %d]%s';

$orders = (int) ($argv[1] ?? DEFAULT_ORDERS);

if ($orders < 1) {
    fwrite(STDERR, sprintf(USAGE_FORMAT, DEFAULT_ORDERS, PHP_EOL));
    exit(2);
}

$startedAt = time();

try {
    ContainerFactory::create()->get(GenerateSalesHistoryService::class)->execute(
        $orders,
        static function (int $done, int $total) use ($startedAt): void {
            fwrite(STDOUT, sprintf(PROGRESS_FORMAT, $done, $total, intdiv(100 * $done, $total), time() - $startedAt));
        },
    );
} catch (SalesHistoryAlreadyExists $exception) {
    fwrite(STDERR, sprintf(ERROR_FORMAT, $exception->getMessage(), PHP_EOL));
    exit(3);
}

fwrite(STDOUT, sprintf(SUMMARY_FORMAT, PHP_EOL, PHP_EOL));
