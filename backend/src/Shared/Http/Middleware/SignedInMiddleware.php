<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\SessionUserInterface;

final readonly class SignedInMiddleware implements MiddlewareInterface
{
    private const string SIGN_IN_REQUIRED_DETAIL = 'Sign in to continue.';

    public function __construct(private SessionUserInterface $user, private Responder $responder)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        if (!$this->user->isSignedIn()) {
            $this->responder->error($request, ResponseStatusEnum::UNAUTHORIZED, self::SIGN_IN_REQUIRED_DETAIL);

            return;
        }

        $next->handle($request);
    }
}
