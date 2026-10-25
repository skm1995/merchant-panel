<?php

declare(strict_types=1);

use App\Auth\Controller\AuthController;
use App\Cart\Controller\CartApiController;
use App\Catalog\Controller\ProductApiController;
use App\Home\Controller\HomeController;
use App\Order\Controller\OrderApiController;
use App\Payment\Controller\PaymentApiController;
use App\Report\Controller\OrderReportController;
use App\Report\Controller\ReportApiController;
use App\Shared\DependencyInjection\ContainerFactory;
use App\Shared\Http\Request;
use App\Shared\Http\Router;
use App\Shared\Http\Session;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = ContainerFactory::create();
$container->get(Session::class)->start();
$router = $container->get(Router::class);

foreach ([
    HomeController::class,
    AuthController::class,
    ProductApiController::class,
    CartApiController::class,
    OrderApiController::class,
    PaymentApiController::class,
    ReportApiController::class,
    OrderReportController::class,
] as $controller) {
    $router->registerController($container->get($controller));
}

$router->dispatch(Request::fromGlobals());
