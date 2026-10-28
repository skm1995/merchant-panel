<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Fixture\Bus;

final class CountItemsHandler
{
    public function handle(CountItemsQuery $query): int
    {
        return 3;
    }
}
