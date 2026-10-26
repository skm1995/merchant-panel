<?php

declare(strict_types=1);

namespace App\Shared\Database\Enum;

enum IsolationLevelEnum: string
{
    case READ_COMMITTED = 'READ COMMITTED';
    case REPEATABLE_READ = 'REPEATABLE READ';
    case SERIALIZABLE = 'SERIALIZABLE';
}
