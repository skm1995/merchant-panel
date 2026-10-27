<?php

declare(strict_types=1);

use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use Tests\Functional\Support\TestUserData;

/**
 * Recreates the shared demo merchant account in the test database; bin/ci runs it before the
 * Playwright tests, which sign in with it, using the credentials from docker/env/demo.env.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

const EMAIL_VARIABLE = 'DEMO_MERCHANT_EMAIL';
const PASSWORD_VARIABLE = 'DEMO_MERCHANT_PASSWORD';

$pdo = DatabaseConnection::createFromEnvironment();
$email = Environment::get(EMAIL_VARIABLE);

TestUserData::delete($pdo, $email);
TestUserData::createMerchant($pdo, $email, Environment::get(PASSWORD_VARIABLE));
