<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Exception\PreconditionRequiredException;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;

/**
 * Answers requests that the HTTP layer rejected while parsing them.
 */
final readonly class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(private Responder $responder)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        try {
            $next->handle($request);
        } catch (InvalidRequestException $exception) {
            $this->responder->error($request, ResponseStatusEnum::BAD_REQUEST, $exception->getMessage());
        } catch (PreconditionRequiredException $exception) {
            $this->responder->error($request, ResponseStatusEnum::PRECONDITION_REQUIRED, $exception->getMessage());
        }
    }
}
