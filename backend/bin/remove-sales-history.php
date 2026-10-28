<?php

declare(strict_types=1);

use App\Analytics\Service\RemoveSalesHistoryService;
use App\Shared\DependencyInjection\ContainerFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

const PROGRESS_FORMAT = "\rRemoved %d orders in %ds.";
const SUMMARY_FORMAT = '%sRemoved %d history and simulation orders with their payments, carts, and products, and gave the disk space back.%s';

$startedAt = time();
$removed = ContainerFactory::create()->get(RemoveSalesHistoryService::class)->execute(
    static function (int $removed) use ($startedAt): void {
        fwrite(STDOUT, sprintf(PROGRESS_FORMAT, $removed, time() - $startedAt));
    },
);

fwrite(STDOUT, sprintf(SUMMARY_FORMAT, PHP_EOL, $removed, PHP_EOL));
