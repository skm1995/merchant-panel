<?php

declare(strict_types=1);

namespace Tests\Functional\Database;

use App\Shared\Database\HydratedRecord;

#[HydratedRecord]
final readonly class ExampleRecord
{
    public int $id;
    public string $name;
}
