<?php

declare(strict_types=1);

use App\Auth\Exception\AuthException;
use App\Auth\Service\GrantMerchantAccessService;
use App\Shared\DependencyInjection\ContainerFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

const USAGE = 'Usage: php bin/grant-merchant.php <email>';
const GRANTED_FORMAT = '%s now has access to the merchant panel.';
const EMAIL_ARGUMENT = 1;
const FAILURE = 1;

$email = $argv[EMAIL_ARGUMENT] ?? null;

if (!is_string($email)) {
    fwrite(STDERR, USAGE . PHP_EOL);
    exit(FAILURE);
}

try {
    ContainerFactory::create()->get(GrantMerchantAccessService::class)->execute($email);
} catch (AuthException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(FAILURE);
}

fwrite(STDOUT, sprintf(GRANTED_FORMAT, $email) . PHP_EOL);
