<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

use Closure;

final class ContainerFactory
{
    private const string DEFINITIONS_PATH = '%s/config/services.php';

    public static function create(): Container
    {
        /** @var array<string, class-string|Closure(Container): object> $definitions */
        $definitions = require sprintf(self::DEFINITIONS_PATH, dirname(__DIR__, 3));

        return new Container($definitions);
    }
}
