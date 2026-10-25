<?php

declare(strict_types=1);

use App\Auth\Controller\AuthController;
use App\Auth\Infrastructure\Messenger\AuthMessengerRuntime;
use App\Auth\Repository\PdoEmailVerificationTokenRepository;
use App\Auth\Repository\PdoUserRepository;
use App\Auth\Service\ConfirmEmailService;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\LogoutUserService;
use App\Auth\Service\RegisterUserService;
use App\Auth\Service\UserAuthService;
use App\Home\Controller\HomeController;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ServerParameterEnum;
use App\Shared\Http\Router;
use App\Shared\Http\Session;

require dirname(__DIR__) . '/vendor/autoload.php';

$session = new Session();
$session->start();
$csrfToken = new CsrfToken($session);

$requestMethod = $_SERVER[ServerParameterEnum::REQUEST_METHOD->value]
    ?? HttpMethodEnum::GET->value;
$requestUri = $_SERVER[ServerParameterEnum::REQUEST_URI->value]
    ?? HomeController::ROUTE_PATH;

if (!is_string($requestMethod)) {
    $requestMethod = HttpMethodEnum::GET->value;
}

if (!is_string($requestUri)) {
    $requestUri = HomeController::ROUTE_PATH;
}

$pdo = DatabaseConnection::createFromEnvironment();
$userRepository = new PdoUserRepository($pdo);
$tokenRepository = new PdoEmailVerificationTokenRepository($pdo);
$userAuthService = new UserAuthService($userRepository);
$registerUserService = new RegisterUserService(
    $userAuthService,
    $tokenRepository,
    AuthMessengerRuntime::createQueueFromEnvironment(),
);
$authController = new AuthController(
    $registerUserService,
    new LoginUserService($userAuthService, $session),
    new LogoutUserService($session),
    new ConfirmEmailService($tokenRepository),
    $csrfToken,
);
$homeController = new HomeController($session, $csrfToken);
$router = new Router($csrfToken);
$router->registerController($homeController);
$router->registerController($authController);

$requestPath = parse_url($requestUri, PHP_URL_PATH);

$router->dispatch(
    $requestMethod,
    is_string($requestPath) ? $requestPath : HomeController::ROUTE_PATH,
);
