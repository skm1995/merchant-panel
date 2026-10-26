<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Request;
use App\Shared\Logging\CorrelationId;
use Symfony\Component\Uid\Uuid;

/**
 * Gives each request an id for its logs: a well-formed X-Request-Id from the client or a new
 * UUID, returned in the response so callers can quote it when reporting a problem.
 */
final readonly class RequestIdMiddleware implements MiddlewareInterface
{
    private const string ACCEPTED_ID_PATTERN = '/^[A-Za-z0-9-]{8,64}$/';
    private const string HEADER_FORMAT = '%s: %s';

    public function __construct(private CorrelationId $correlation)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): void
    {
        $provided = $request->header(HeaderNameEnum::REQUEST_ID->value);
        $id = $provided !== null && preg_match(self::ACCEPTED_ID_PATTERN, $provided) === 1 ? $provided : Uuid::v4()->toRfc4122();

        $this->correlation->set($id);
        header(sprintf(self::HEADER_FORMAT, HeaderNameEnum::REQUEST_ID->value, $id));
        $next->handle($request);
    }
}
