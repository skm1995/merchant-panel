<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$pdo = App\Core\DatabaseConnection::createFromEnvironment();
$userRepository = new App\Repository\PdoUserRepository($pdo);
$tokenRepository = new App\Repository\PdoEmailVerificationTokenRepository($pdo);
$userAuthService = new App\Service\Auth\UserAuthService($userRepository);
$messengerRuntime = App\Service\Auth\AuthMessengerRuntime::createFromEnvironment();
$registerUserService = new App\Service\Auth\RegisterUserService(
    $userAuthService,
    $tokenRepository,
    $messengerRuntime->queue(),
);
$loginUserService = new App\Service\Auth\LoginUserService($userAuthService);
$logoutUserService = new App\Service\Auth\LogoutUserService();
$authService = new App\Service\Auth\AuthService(
    $registerUserService,
    $loginUserService,
    $logoutUserService,
    new App\Service\Auth\ConfirmEmailService($tokenRepository),
);
$authController = new App\Controller\AuthController($authService);
$homeModel = new App\Model\HomeModel();
$homeController = new App\Controller\HomeController($homeModel);
$router = new App\Core\Router();
$router->get(
    App\Controller\HomeController::ROUTE_PATH,
    static function () use ($homeController): void {
        $homeController->index();
    },
);
$router->get(App\Controller\AuthController::REGISTER_ROUTE_PATH, [$authController, 'showRegister']);
$router->post(App\Controller\AuthController::REGISTER_ROUTE_PATH, [$authController, 'register']);
$router->get(App\Controller\AuthController::LOGIN_ROUTE_PATH, [$authController, 'showLogin']);
$router->post(App\Controller\AuthController::LOGIN_ROUTE_PATH, [$authController, 'login']);
$router->post(App\Controller\AuthController::LOGOUT_ROUTE_PATH, [$authController, 'logout']);
$router->get(
    App\Controller\AuthController::CONFIRMATION_SENT_ROUTE_PATH,
    static function () use ($authController): void {
        $authController->showConfirmationSent();
    },
);
$router->get(
    App\Controller\AuthController::CONFIRM_ROUTE_PATH,
    static function () use ($authController): void {
        $authController->confirm();
    },
);
$router->get(
    App\Controller\AuthController::EMAIL_CONFIRMED_ROUTE_PATH,
    static function () use ($authController): void {
        $authController->showEmailConfirmed();
    },
);

$requestPath = parse_url($requestUri, PHP_URL_PATH);

$router->dispatch(
    $requestMethod,
    is_string($requestPath) ? $requestPath : App\Controller\HomeController::ROUTE_PATH,
);
