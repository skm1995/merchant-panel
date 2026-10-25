<?php

declare(strict_types=1);

namespace Tests\Unit\Home\Controller;

use App\Home\Controller\HomeController;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    private const string HOME_ROUTE_PATH = '/';
    private const string HOME_HEADING = '<h1>PHP MVC Playground</h1>';

    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            (new Session())->start();
        }

        $this->originalSession = $_SESSION;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    public function testRendersHomePage(): void
    {
        $session = new Session();
        $controller = new HomeController($session, new CsrfToken($session));
        ob_start();

        $controller->index();
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString(self::HOME_HEADING, $output);
    }

    public function testExposesHomeRoutePath(): void
    {
        self::assertSame(self::HOME_ROUTE_PATH, HomeController::ROUTE_PATH);
    }
}
