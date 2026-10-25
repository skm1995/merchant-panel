<?php

declare(strict_types=1);

use App\Shared\Database\DatabaseSettings;

$database = DatabaseSettings::fromEnvironment();

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds',
    ],
    'environments' => [
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $database->host,
            'name' => $database->database,
            'user' => $database->username,
            'pass' => $database->password,
            'port' => $database->port,
            'charset' => DatabaseSettings::CHARSET,
        ],
    ],
    'version_order' => 'creation',
];
