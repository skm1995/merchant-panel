<?php

declare(strict_types=1);

use App\Auth\Controller\AuthController;
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
use App\Catalog\Infrastructure\Cache\CachedProductRepository;
use App\Catalog\Infrastructure\Cache\ProductCache;
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
use App\Order\Service\OrderSettlement;
use App\Payment\Command\HandlePaymentWebhookHandler;
use App\Payment\Command\StartPaymentHandler;
use App\Payment\Controller\PaymentApiController;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use App\Payment\Query\GetPaymentHandler;
use App\Payment\Repository\PdoPaymentReadRepository;
use App\Payment\Repository\PdoPaymentRepository;
use App\Payment\Repository\PdoWebhookInboxRepository;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Database\PdoTransactionRunner;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Router;
use App\Shared\Http\Session;
use App\Shared\Cache\RedisCacheStore;
use App\Shared\Cache\RedisConnection;
use App\Shared\Cache\StampedeProtectedCache;
use App\Shared\Config\Environment;
use App\Shared\Messaging\Outbox\PdoOutbox;
use Symfony\Component\Clock\NativeClock;

require dirname(__DIR__) . '/vendor/autoload.php';

const ORDER_RESERVATION_SECONDS = 'ORDER_RESERVATION_SECONDS';

$session = new Session();
$session->start();
$csrfToken = new CsrfToken($session);
$responder = new Responder($csrfToken);
$currentUser = new CurrentUser($session);

$pdo = DatabaseConnection::createFromEnvironment();
$transactions = new PdoTransactionRunner($pdo);
$tokenRepository = new PdoEmailVerificationTokenRepository($pdo);
$outbox = new PdoOutbox($pdo);
$clock = new NativeClock();
$userAuthService = new UserAuthService(new PdoUserRepository($pdo));

$router = new Router($csrfToken, $responder);
$router->registerController(new HomeController($currentUser, $responder));
$router->registerController(new AuthController(
    new RegisterUserService(
        $userAuthService,
        $tokenRepository,
        $outbox,
        $transactions,
        $clock,
    ),
    new LoginUserService($userAuthService, $currentUser),
    new ConfirmEmailService($tokenRepository, $transactions),
    $currentUser,
    $responder,
));
$productRepository = new PdoProductRepository($pdo);
$cacheStore = new RedisCacheStore(RedisConnection::createFromEnvironment());
$productCatalog = new ProductCatalog(new CachedProductRepository(
    $productRepository,
    new ProductCache(new StampedeProtectedCache($cacheStore, $clock), $cacheStore),
));
$cartService = new CartService(new PdoCartRepository($pdo), $productCatalog, $transactions);
$stockReservation = new StockReservation($productRepository, $outbox, $clock);
$orderRepository = new PdoOrderRepository($pdo);
$router->registerController(new ProductApiController($productCatalog, $responder));
$router->registerController(new CartApiController($cartService, $responder));
$getOrder = new GetOrderHandler(new PdoOrderReadRepository($pdo));
$paymentRepository = new PdoPaymentRepository($pdo);
$paymentGateways = new PaymentGatewayFactory($clock);
$router->registerController(new OrderApiController(
    new PlaceOrderHandler(
        $orderRepository,
        $cartService,
        $stockReservation,
        $outbox,
        $transactions,
        $clock,
        Environment::positiveInteger(ORDER_RESERVATION_SECONDS),
    ),
    $getOrder,
    new CancelOrderHandler($orderRepository, $stockReservation, $transactions),
    $responder,
));
$router->registerController(new PaymentApiController(
    new StartPaymentHandler($paymentRepository, $getOrder, $paymentGateways),
    new GetPaymentHandler(new PdoPaymentReadRepository($pdo)),
    new HandlePaymentWebhookHandler(
        $paymentGateways,
        new PdoWebhookInboxRepository($pdo),
        $paymentRepository,
        new OrderSettlement($orderRepository, $stockReservation),
        $transactions,
    ),
    $responder,
));
$router->dispatch(Request::fromGlobals());
