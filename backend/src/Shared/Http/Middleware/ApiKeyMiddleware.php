<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Config\Environment;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\RequireApiKey;
use App\Shared\Http\Responder;

final readonly class ApiKeyMiddleware implements MiddlewareInterface
{
    private const string INVALID_KEY_DETAIL = 'A valid X-Api-Key header is required.';

    public function __construct(private RequireApiKey $requirement, private Responder $responder)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        $provided = $request->header(HeaderNameEnum::API_KEY->value);

        if ($provided === null || !hash_equals(Environment::get($this->requirement->environmentKey), $provided)) {
            $this->responder->error($request, ResponseStatusEnum::UNAUTHORIZED, self::INVALID_KEY_DETAIL);

            return;
        }

        $next->handle($request);
    }
}
