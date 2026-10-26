<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture\Services;

use App\Shared\DependencyInjection\ServiceFactory;

final class Runtime
{
    public function __construct(public readonly ExternalConnection $connection)
    {
    }

    #[ServiceFactory]
    public function channel(): ChannelInterface
    {
        return new readonly class ($this) implements ChannelInterface {
            public function __construct(private Runtime $runtime)
            {
            }

            public function runtime(): Runtime
            {
                return $this->runtime;
            }
        };
    }
}
