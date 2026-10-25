<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Route;
use App\Shared\Http\Router;
use App\Shared\Http\Session;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private const string ROUTE_PATH = '/';
    private const string MISSING_ROUTE_PATH = '/missing';
    private const string DISPATCHED_MESSAGE = 'route dispatched';
    private const string NOT_FOUND_MESSAGE = '404 Not Found';
    private const string FORBIDDEN_MESSAGE = '403 Forbidden';
    private const string POST_VALUE = 'post route dispatched';
    private const string CSRF_FIELD = CsrfToken::SESSION_KEY;
    private CsrfToken $csrfToken;
    /** @var array<string, mixed> */
    private array $originalSession;
    /** @var array<string, mixed> */
    private array $originalPost;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            (new Session())->start();
        }

        $this->originalSession = $_SESSION;
        $this->originalPost = $_POST;
        $_SESSION = [];
        $_POST = [];
        $this->csrfToken = new CsrfToken(new Session());
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        $_POST = $this->originalPost;
    }

    public function testDispatchesAttributedGetRoute(): void
    {
        $controller = new class () {
            public const string PATH = '/';
            public const string MESSAGE = 'route dispatched';
            public bool $called = false;

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function index(): void
            {
                $this->called = true;
                echo self::MESSAGE;
            }
        };
        $router = $this->router();
        $router->registerController($controller);
        ob_start();

        $router->dispatch(HttpMethodEnum::GET->value, self::ROUTE_PATH);
        $output = ob_get_clean();

        self::assertTrue($controller->called);
        self::assertSame(self::DISPATCHED_MESSAGE, $output);
    }

    public function testReturnsNotFoundForUnknownRoute(): void
    {
        $router = $this->router();
        http_response_code(200);
        ob_start();

        $router->dispatch(HttpMethodEnum::GET->value, self::MISSING_ROUTE_PATH);
        $output = ob_get_clean();

        self::assertSame(404, http_response_code());
        self::assertSame(self::NOT_FOUND_MESSAGE, $output);
    }

    public function testDispatchesAttributedPostRoute(): void
    {
        $controller = new class () {
            public const string PATH = '/';
            public const string MESSAGE = 'post route dispatched';

            #[Route(HttpMethodEnum::POST, self::PATH)]
            public function submit(): void
            {
                echo self::MESSAGE;
            }
        };
        $_POST[self::CSRF_FIELD] = $this->csrfToken->get();
        $router = $this->router();
        $router->registerController($controller);
        ob_start();

        $router->dispatch(HttpMethodEnum::POST->value, self::ROUTE_PATH);
        $output = ob_get_clean();

        self::assertSame(self::POST_VALUE, $output);
    }

    public function testRejectsDuplicateAttributedRoutes(): void
    {
        $controller = new class () {
            public const string PATH = '/';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function first(): void
            {
            }

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function second(): void
            {
            }
        };
        $router = $this->router();

        $this->expectException(LogicException::class);
        $router->registerController($controller);
    }

    public function testRejectsPostRequestsWithoutCsrfToken(): void
    {
        $controller = new class () {
            private const string PATH = '/';
            public bool $called = false;

            #[Route(HttpMethodEnum::POST, self::PATH)]
            public function submit(): void
            {
                $this->called = true;
            }
        };
        $router = $this->router();
        $router->registerController($controller);

        ob_start();
        $router->dispatch(HttpMethodEnum::POST->value, self::ROUTE_PATH);
        $output = ob_get_clean();

        self::assertFalse($controller->called);
        self::assertSame(403, http_response_code());
        self::assertSame(self::FORBIDDEN_MESSAGE, $output);
    }

    private function router(): Router
    {
        return new Router($this->csrfToken);
    }
}
