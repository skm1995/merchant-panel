<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Http\CsrfToken;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Router;
use App\Shared\Http\Session;
use PHPUnit\Framework\Assert;

/**
 * Dispatches requests to controllers through the real router in unit tests, so routing,
 * path constraints, and exception mapping are exercised without a web server.
 */
final class HttpHarness
{
    public static function responder(?Session $session = null): Responder
    {
        return new Responder(new CsrfToken($session ?? new Session()));
    }

    public static function dispatch(Request $request, object ...$controllers): string
    {
        $router = new Router(new CsrfToken(new Session()), new Session(), self::responder());

        foreach ($controllers as $controller) {
            $router->registerController($controller);
        }

        ob_start();
        $router->dispatch($request);
        $output = ob_get_clean();
        Assert::assertIsString($output);

        return $output;
    }

    /** @return array<string, mixed> */
    public static function json(Request $request, object ...$controllers): array
    {
        $body = json_decode(self::dispatch($request, ...$controllers), true, flags: JSON_THROW_ON_ERROR);
        Assert::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return $body;
    }
}
