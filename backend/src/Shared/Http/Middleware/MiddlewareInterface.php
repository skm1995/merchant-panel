<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Request;

/**
 * One cross-cutting HTTP policy. It either answers the request itself or passes it to
 * the next handler, optionally acting before and after it.
 */
interface MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $next): void;
}
