<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\ControllerDiscovery;
use App\Shared\Http\Route;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\OpenApiOperations;

/**
 * Every API route has an operation in openapi/openapi.yaml and every operation a route, so the
 * contract the frontend and integrations rely on cannot drift from the code. The legacy pages
 * outside /api/ serve HTML and are not part of the contract.
 */
final class RouteContractTest extends TestCase
{
    private const string API_PREFIX = '/api/';

    public function testEveryApiRouteHasAnOperationInTheContractAndTheOtherWayRound(): void
    {
        self::assertSame(OpenApiOperations::all(), self::apiRoutes());
    }

    public function testComparesPathsWhateverTheirParametersAreCalled(): void
    {
        self::assertSame('GET /api/v1/carts/{}/items/{}', OpenApiOperations::key('get', '/api/v1/carts/{id:[0-9a-f]{8}-[0-9a-f]{4}}/items/{productId:\d+}'));
        self::assertTrue(OpenApiOperations::matches('PUT /api/v1/carts/{}/items/{}', 'PUT', '/api/v1/carts/4f1c/items/7?x=1'));
        self::assertFalse(OpenApiOperations::matches('GET /api/v1/carts/{}', 'GET', '/api/v1/carts/4f1c/items/7'));
    }

    /** @return list<string> */
    private static function apiRoutes(): array
    {
        $routes = [];

        foreach (ControllerDiscovery::findInSource() as $controller) {
            foreach (new ReflectionClass($controller)->getMethods() as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();

                    if (str_starts_with($route->path, self::API_PREFIX)) {
                        $routes[] = OpenApiOperations::key($route->method->value, $route->path);
                    }
                }
            }
        }

        sort($routes);

        return $routes;
    }
}
