<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$requestMethod = $_SERVER[App\Enum\ServerParameterEnum::REQUEST_METHOD->value]
    ?? App\Enum\HttpMethodEnum::GET->value;
$requestUri = $_SERVER[App\Enum\ServerParameterEnum::REQUEST_URI->value]
    ?? App\Controller\HomeController::ROUTE_PATH;

if (!is_string($requestMethod)) {
    $requestMethod = App\Enum\HttpMethodEnum::GET->value;
}

if (!is_string($requestUri)) {
    $requestUri = App\Controller\HomeController::ROUTE_PATH;
}

$homeModel = new App\Model\HomeModel();
$homeController = new App\Controller\HomeController($homeModel);
$router = new App\Core\Router();
$router->get(
    App\Controller\HomeController::ROUTE_PATH,
    static function () use ($homeController): void {
        $homeController->index();
    },
);

$requestPath = parse_url($requestUri, PHP_URL_PATH);

$router->dispatch(
    $requestMethod,
    is_string($requestPath) ? $requestPath : App\Controller\HomeController::ROUTE_PATH,
);
