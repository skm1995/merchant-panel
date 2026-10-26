<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Request;

/**
 * Handles a request by writing its response.
 */
interface RequestHandlerInterface
{
    public function handle(Request $request): void;
}
