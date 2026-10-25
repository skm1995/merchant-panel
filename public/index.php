<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\HomeController;
use App\Core\DatabaseConnection;
use App\Core\Router;
use App\Enum\HttpMethodEnum;
use App\Enum\ServerParameterEnum;
use App\Model\HomeModel;
use App\Repository\PdoEmailVerificationTokenRepository;
use App\Repository\PdoUserRepository;
use App\Service\Auth\AuthMessengerRuntime;
use App\Service\Auth\AuthService;
use App\Service\Auth\ConfirmEmailService;
use App\Service\Auth\LoginUserService;
use App\Service\Auth\LogoutUserService;
use App\Service\Auth\RegisterUserService;
use App\Service\Auth\UserAuthService;

require dirname(__DIR__) . '/vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$messengerRuntime = AuthMessengerRuntime::createFromEnvironment();
$registerUserService = new RegisterUserService(
    $userAuthService,
    $tokenRepository,
    $messengerRuntime->queue(),
);
$loginUserService = new LoginUserService($userAuthService);
$logoutUserService = new LogoutUserService();
$authService = new AuthService(
    $registerUserService,
    $loginUserService,
    $logoutUserService,
    new ConfirmEmailService($tokenRepository),
);
$authController = new AuthController($authService);
$homeModel = new HomeModel();
$homeController = new HomeController($homeModel);
$router = new Router();
$router->get(
    HomeController::ROUTE_PATH,
    static function () use ($homeController): void {
        $homeController->index();
    },
);
$router->get(AuthController::REGISTER_ROUTE_PATH, [$authController, 'showRegister']);
$router->post(AuthController::REGISTER_ROUTE_PATH, [$authController, 'register']);
$router->get(AuthController::LOGIN_ROUTE_PATH, [$authController, 'showLogin']);
$router->post(AuthController::LOGIN_ROUTE_PATH, [$authController, 'login']);
$router->post(AuthController::LOGOUT_ROUTE_PATH, [$authController, 'logout']);
$router->get(
    AuthController::CONFIRMATION_SENT_ROUTE_PATH,
    static function () use ($authController): void {
        $authController->showConfirmationSent();
    },
);
$router->get(
    AuthController::CONFIRM_ROUTE_PATH,
    static function () use ($authController): void {
        $authController->confirm();
    },
);
$router->get(
    AuthController::EMAIL_CONFIRMED_ROUTE_PATH,
    static function () use ($authController): void {
        $authController->showEmailConfirmed();
    },
);

$requestPath = parse_url($requestUri, PHP_URL_PATH);

$router->dispatch(
    $requestMethod,
    is_string($requestPath) ? $requestPath : HomeController::ROUTE_PATH,
);
