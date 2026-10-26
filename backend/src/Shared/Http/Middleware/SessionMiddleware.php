<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Request;
use App\Shared\Http\Session;

final readonly class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private Session $session)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        $this->session->start();
        $next->handle($request);
    }
}
