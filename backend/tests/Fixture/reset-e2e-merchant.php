<?php

declare(strict_types=1);

use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use Tests\Functional\Support\TestUserData;

/**
 * Recreates the merchant account the Playwright tests sign in with; bin/ci runs it before
 * them, with the credentials from docker/env/e2e.env.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

const EMAIL_VARIABLE = 'E2E_MERCHANT_EMAIL';
const PASSWORD_VARIABLE = 'E2E_MERCHANT_PASSWORD';

$pdo = DatabaseConnection::createFromEnvironment();
$email = Environment::get(EMAIL_VARIABLE);

TestUserData::delete($pdo, $email);
TestUserData::createMerchant($pdo, $email, Environment::get(PASSWORD_VARIABLE));
