<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Controller\HomeController;
use App\Core\Controller;
use App\Core\Router;
use App\Model\HomeModel;
use App\Model\HomeModelInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ClassConstantTypesTest extends TestCase
{
    private const string ROUTE_CONSTANT_NAME = 'ROUTE_PATH';

    private const array APPLICATION_CLASSES = [
        Controller::class,
        Router::class,
        HomeController::class,
        HomeModel::class,
        HomeModelInterface::class,
    ];

    public function testApplicationClassConstantsAreTypedAndPublicOnlyForControllerRoutes(): void
    {
        foreach (self::APPLICATION_CLASSES as $className) {
            $reflectionClass = new ReflectionClass($className);

            foreach ($reflectionClass->getReflectionConstants() as $constant) {
                self::assertNotNull($constant->getType(), $className . '::' . $constant->getName());

                if ($constant->isPublic()) {
                    self::assertSame(HomeController::class, $className);
                    self::assertSame(self::ROUTE_CONSTANT_NAME, $constant->getName());
                }
            }
        }
    }
}
