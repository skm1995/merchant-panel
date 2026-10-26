<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Route
{
    public function __construct(public HttpMethodEnum $method, public string $path)
    {
    }
}
