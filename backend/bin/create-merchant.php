<?php

declare(strict_types=1);

use App\Auth\Exception\AuthException;
use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Service\CreateMerchantAccountService;
use App\Shared\Config\Environment;
use App\Shared\DependencyInjection\ContainerFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

// The password comes from the environment rather than an argument, which other users of the
// server could read in the process list.
const USAGE = 'Usage: MERCHANT_PASSWORD=<password> php bin/create-merchant.php <email>';
const PASSWORD_VARIABLE = 'MERCHANT_PASSWORD';
const CREATED_FORMAT = '%s can now sign in to the merchant panel.';
const EMAIL_ARGUMENT = 1;
const FAILURE = 1;
const ALREADY_EXISTS = 3;

$email = $argv[EMAIL_ARGUMENT] ?? null;
$password = Environment::find(PASSWORD_VARIABLE);

if (!is_string($email) || $password === null) {
    fwrite(STDERR, USAGE . PHP_EOL);
    exit(FAILURE);
}

try {
    ContainerFactory::create()->get(CreateMerchantAccountService::class)->execute($email, $password);
} catch (DuplicateEmailException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(ALREADY_EXISTS);
} catch (AuthException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(FAILURE);
}

fwrite(STDOUT, sprintf(CREATED_FORMAT, $email) . PHP_EOL);
