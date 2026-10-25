<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\HomeController;
use App\Model\HomeModelInterface;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    private const string MESSAGE_METHOD = 'message';
    private const string MESSAGE = 'Injected model message';
    private const string HOME_ROUTE_PATH = '/';

    public function testRendersMessageProvidedByInjectedModel(): void
    {
        $homeModel = $this->createMock(HomeModelInterface::class);
        $homeModel->expects(self::once())
            ->method(self::MESSAGE_METHOD)
            ->willReturn(self::MESSAGE);
        $controller = new HomeController($homeModel);
        ob_start();

        $controller->index();
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString(self::MESSAGE, $output);
    }

    public function testExposesHomeRoutePath(): void
    {
        self::assertSame(self::HOME_ROUTE_PATH, HomeController::ROUTE_PATH);
    }
}
