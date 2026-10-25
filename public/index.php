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
use App\Catalog\Controller\ProductApiController;
use App\Catalog\Repository\PdoProductRepository;
use App\Catalog\Service\ProductCatalog;
use App\Home\Controller\HomeController;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Database\PdoTransactionRunner;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Router;
use App\Shared\Http\Session;

require dirname(__DIR__) . '/vendor/autoload.php';

$session = new Session();
$session->start();
$csrfToken = new CsrfToken($session);
$responder = new Responder($csrfToken);
$currentUser = new CurrentUser($session);

$pdo = DatabaseConnection::createFromEnvironment();
$tokenRepository = new PdoEmailVerificationTokenRepository($pdo, new PdoTransactionRunner($pdo));
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
$router->registerController(new ProductApiController(
    new ProductCatalog(new PdoProductRepository($pdo)),
    $responder,
));
$router->dispatch(Request::fromGlobals());
