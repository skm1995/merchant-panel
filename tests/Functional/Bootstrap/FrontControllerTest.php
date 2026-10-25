<?php

declare(strict_types=1);

namespace Tests\Functional\Bootstrap;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ServerParameterEnum;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;

final class FrontControllerTest extends TestCase
{
    private const string FRONT_CONTROLLER_PATH = '/public/index.php';
    private const string EXPECTED_MESSAGE = 'PHP MVC Playground';

    public function testFrontControllerBuildsDependenciesAndDispatchesHomeRoute(): void
    {
        $originalServer = $_SERVER;
        $_SERVER[ServerParameterEnum::REQUEST_METHOD->value] = HttpMethodEnum::GET->value;
        $_SERVER[ServerParameterEnum::REQUEST_URI->value] = Request::ROOT_PATH;
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
