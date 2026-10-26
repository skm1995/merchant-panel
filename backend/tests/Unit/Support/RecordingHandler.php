<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Http\Middleware\RequestHandlerInterface;
use App\Shared\Http\Request;

/**
 * Final request handler for middleware tests: records the requests that reached it.
 */
final class RecordingHandler implements RequestHandlerInterface
{
    /** @var list<Request> */
    public array $handled = [];

    public function handle(Request $request): void
    {
        $this->handled[] = $request;
    }
}
