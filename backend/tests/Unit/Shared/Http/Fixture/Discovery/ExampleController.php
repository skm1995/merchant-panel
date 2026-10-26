<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Fixture\Discovery;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Route;

final class ExampleController
{
    private const string PATH = '/discovered';

    #[Route(HttpMethodEnum::GET, self::PATH)]
    public function show(): void
    {
    }
}
