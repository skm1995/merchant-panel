<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture\Services;

use App\Shared\DependencyInjection\Env;
use App\Shared\DependencyInjection\ServiceFactory;

final class ConnectionFactory
{
    #[ServiceFactory]
    public static function connect(#[Env('FIXTURE_DSN')] string $dsn): ExternalConnection
    {
        return new ExternalConnection($dsn);
    }
}
