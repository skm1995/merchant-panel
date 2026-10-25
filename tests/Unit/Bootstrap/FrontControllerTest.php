<?php

declare(strict_types=1);

namespace Tests\Unit\Bootstrap;

use App\Controller\HomeController;
use App\Enum\HttpMethodEnum;
use App\Enum\ServerParameterEnum;
use PHPUnit\Framework\TestCase;

final class FrontControllerTest extends TestCase
{
    private const string FRONT_CONTROLLER_PATH = '/public/index.php';
    private const string EXPECTED_MESSAGE = 'PHP MVC playground is running.';

    public function testFrontControllerBuildsDependenciesAndDispatchesHomeRoute(): void
    {
        $originalServer = $_SERVER;
        $_SERVER[ServerParameterEnum::REQUEST_METHOD->value] = HttpMethodEnum::GET->value;
        $_SERVER[ServerParameterEnum::REQUEST_URI->value] = HomeController::ROUTE_PATH;
        ob_start();

        require dirname(__DIR__, 3) . self::FRONT_CONTROLLER_PATH;
        $output = ob_get_clean();
        $_SERVER = $originalServer;

        self::assertIsString($output);
        self::assertStringContainsString(self::EXPECTED_MESSAGE, $output);
    }

    public function testFrontControllerUsesDefaultsForInvalidServerParameters(): void
    {
        $originalServer = $_SERVER;
        $_SERVER = [
            ServerParameterEnum::REQUEST_METHOD->value => [],
            ServerParameterEnum::REQUEST_URI->value => [],
        ];
        ob_start();

        require dirname(__DIR__, 3) . self::FRONT_CONTROLLER_PATH;
        $output = ob_get_clean();
        $_SERVER = $originalServer;

        self::assertIsString($output);
        self::assertStringContainsString(self::EXPECTED_MESSAGE, $output);
    }
}
