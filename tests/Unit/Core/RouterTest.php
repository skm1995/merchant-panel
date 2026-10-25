<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Router;
use App\Enum\HttpMethodEnum;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private const string ROUTE_PATH = '/';
    private const string MISSING_ROUTE_PATH = '/missing';
    private const string DISPATCHED_MESSAGE = 'route dispatched';
    private const string NOT_FOUND_MESSAGE = '404 Not Found';

    public function testDispatchesRegisteredGetRoute(): void
    {
        $controller = new class () {
            public bool $called = false;
        };
        $router = new Router();
        $router->get(self::ROUTE_PATH, static function () use ($controller): void {
            $controller->called = true;
            echo self::DISPATCHED_MESSAGE;
        });
        ob_start();

        $router->dispatch(HttpMethodEnum::GET->value, self::ROUTE_PATH);
        $output = ob_get_clean();

        self::assertTrue($controller->called);
        self::assertSame(self::DISPATCHED_MESSAGE, $output);
    }

    public function testReturnsNotFoundForUnknownRoute(): void
    {
        $router = new Router();
        http_response_code(200);
        ob_start();

        $router->dispatch(HttpMethodEnum::GET->value, self::MISSING_ROUTE_PATH);
        $output = ob_get_clean();

        self::assertSame(404, http_response_code());
        self::assertSame(self::NOT_FOUND_MESSAGE, $output);
        self::assertSame(self::NOT_FOUND_MESSAGE, $router->message());
    }

}
