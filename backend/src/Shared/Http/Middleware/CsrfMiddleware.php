<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;

/**
 * Rejects state-changing requests that rely on the session cookie unless they carry the
 * session's CSRF token in the X-CSRF-Token header.
 */
final readonly class CsrfMiddleware implements MiddlewareInterface
{
    private const string INVALID_TOKEN_DETAIL = 'The CSRF token is missing or invalid.';

    public function __construct(private CsrfToken $csrfToken, private Responder $responder)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        $token = $request->header(HeaderNameEnum::CSRF_TOKEN->value);

        if ($request->method !== HttpMethodEnum::GET->value && !$this->csrfToken->isValid($token)) {
            $this->responder->error($request, ResponseStatusEnum::FORBIDDEN, self::INVALID_TOKEN_DETAIL);

            return;
        }

        $next->handle($request);
    }
}
