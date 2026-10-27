<?php

declare(strict_types=1);

use App\Auth\Service\PurgeUnconfirmedAccountsService;
use App\Shared\DependencyInjection\ContainerFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

const SUMMARY_FORMAT = 'Deleted %d account(s) left unconfirmed for a week.%s';

$deleted = ContainerFactory::create()->get(PurgeUnconfirmedAccountsService::class)->execute();
fwrite(STDOUT, sprintf(SUMMARY_FORMAT, $deleted, PHP_EOL));
