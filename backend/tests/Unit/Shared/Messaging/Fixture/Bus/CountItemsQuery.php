<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Fixture\Bus;

use App\Shared\Messaging\Bus\QueryInterface;

/** @implements QueryInterface<int> */
final readonly class CountItemsQuery implements QueryInterface
{
}
