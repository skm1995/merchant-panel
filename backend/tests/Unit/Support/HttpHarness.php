<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Auth\Infrastructure\Session\CurrentUser;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\LoadTestKey;
use App\Shared\Http\RateLimiter;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Router;
use App\Shared\Http\Session;
use PHPUnit\Framework\Assert;
use Psr\Log\NullLogger;

/**
 * Dispatches requests to controllers through the real router in unit tests, so routing,
 * path constraints, and exception mapping are exercised without a web server.
 */
final class HttpHarness
{
    private const string LOAD_TEST_KEY = 'test-load-test-key';
    public static function responder(): Responder
    {
        return new Responder();
    }

    public static function dispatch(Request $request, object ...$controllers): string
    {
        $router = new Router(
            new CsrfToken(new Session()),
            new Session(),
            self::responder(),
            new CurrentUser(new Session(), new MerchantUserRepository()),
            new RateLimiter(new InMemoryCacheStore(), new FrozenClock(), new NullLogger()),
            new LoadTestKey(self::LOAD_TEST_KEY),
        );

        foreach ($controllers as $controller) {
            $router->registerController($controller::class, static fn (): object => $controller);
        }

        ob_start();
        $router->handle($request);
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
