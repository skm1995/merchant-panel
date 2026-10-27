<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\SessionUserInterface;

/**
 * Lets only merchants through: anonymous visitors must sign in (401), and signed-in users
 * without merchant access are refused (403).
 */
final readonly class MerchantMiddleware implements MiddlewareInterface
{
    private const string SIGN_IN_REQUIRED_DETAIL = 'Sign in to continue.';
    private const string NO_ACCESS_DETAIL = 'Your account has no access to the merchant panel.';

    public function __construct(private SessionUserInterface $user, private Responder $responder)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        if (!$this->user->isSignedIn()) {
            $this->responder->error($request, ResponseStatusEnum::UNAUTHORIZED, self::SIGN_IN_REQUIRED_DETAIL);

            return;
        }

        if (!$this->user->isMerchant()) {
            $this->responder->error($request, ResponseStatusEnum::FORBIDDEN, self::NO_ACCESS_DETAIL);

            return;
        }

        $next->handle($request);
    }
}
