<?php

declare(strict_types=1);

use App\Auth\Controller\AuthController;
use App\Auth\Infrastructure\Messenger\AuthMessengerRuntime;
use App\Auth\Repository\PdoEmailVerificationTokenRepository;
use App\Auth\Repository\PdoUserRepository;
use App\Auth\Service\ConfirmEmailService;
use App\Auth\Service\CurrentUser;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\RegisterUserService;
use App\Auth\Service\UserAuthService;
use App\Cart\Controller\CartApiController;
use App\Cart\Repository\PdoCartRepository;
use App\Cart\Service\CartService;
use App\Catalog\Controller\ProductApiController;
use App\Catalog\Repository\PdoProductRepository;
use App\Catalog\Service\ProductCatalog;
use App\Catalog\Service\StockReservation;
use App\Home\Controller\HomeController;
use App\Order\Command\CancelOrderHandler;
use App\Order\Command\PlaceOrderHandler;
use App\Order\Controller\OrderApiController;
use App\Order\Query\GetOrderHandler;
use App\Order\Repository\PdoOrderReadRepository;
use App\Order\Repository\PdoOrderRepository;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Database\PdoTransactionRunner;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Router;
use App\Shared\Http\Session;
use Symfony\Component\Clock\NativeClock;

require dirname(__DIR__) . '/vendor/autoload.php';

$session = new Session();
$session->start();
$csrfToken = new CsrfToken($session);
$responder = new Responder($csrfToken);
$currentUser = new CurrentUser($session);

$pdo = DatabaseConnection::createFromEnvironment();
$transactions = new PdoTransactionRunner($pdo);
$tokenRepository = new PdoEmailVerificationTokenRepository($pdo, $transactions);
$userAuthService = new UserAuthService(new PdoUserRepository($pdo));

$router = new Router($csrfToken, $responder);
$router->registerController(new HomeController($currentUser, $responder));
$router->registerController(new AuthController(
    new RegisterUserService(
        $userAuthService,
        $tokenRepository,
        AuthMessengerRuntime::createQueueFromEnvironment(),
    ),
    new LoginUserService($userAuthService, $currentUser),
    new ConfirmEmailService($tokenRepository),
    $currentUser,
    $responder,
));
$productRepository = new PdoProductRepository($pdo);
$productCatalog = new ProductCatalog($productRepository);
$cartService = new CartService(new PdoCartRepository($pdo), $productCatalog, $transactions);
$stockReservation = new StockReservation($productRepository);
$orderRepository = new PdoOrderRepository($pdo);
$router->registerController(new ProductApiController($productCatalog, $responder));
$router->registerController(new CartApiController($cartService, $responder));
$router->registerController(new OrderApiController(
    new PlaceOrderHandler($orderRepository, $cartService, $stockReservation, $transactions, new NativeClock()),
    new GetOrderHandler(new PdoOrderReadRepository($pdo)),
    new CancelOrderHandler($orderRepository, $stockReservation, $transactions),
    $responder,
));
$router->dispatch(Request::fromGlobals());
