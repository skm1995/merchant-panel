<?php

declare(strict_types=1);

namespace App\Enum;

enum DatabaseEnvironmentEnum: string
{
    case HOST = 'DB_HOST';
    case PORT = 'DB_PORT';
    case DATABASE = 'DB_DATABASE';
    case USERNAME = 'DB_USERNAME';
    case PASSWORD = 'DB_PASSWORD';
}
