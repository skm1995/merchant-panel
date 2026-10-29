<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Enum\AuthRateLimitEnum;
use App\Auth\Exception\EmailNotVerifiedException;
use App\Auth\Exception\InvalidConfirmationTokenException;
use App\Auth\Exception\InvalidCredentialsException;
use App\Auth\Exception\InvalidEmailException;
use App\Auth\Exception\InvalidResetTokenException;
use App\Auth\Exception\ResendLimitReachedException;
use App\Auth\Exception\UnsupportedPasswordException;
use App\Auth\Exception\WeakPasswordException;
use App\Auth\Request\ConfirmationTokenRequest;
use App\Auth\Request\CredentialsRequest;
use App\Auth\Request\NewPasswordRequest;
use App\Auth\Request\ResetLinkRequest;
use App\Auth\Response\SessionResponse;
use App\Auth\Service\ConfirmEmailService;
use App\Auth\Service\CurrentUserInterface;
use App\Auth\Service\LoginUserService;
use App\Auth\Service\RegisterUserService;
use App\Auth\Service\RequestPasswordResetService;
use App\Auth\Service\ResetPasswordService;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\MapException;
use App\Shared\Http\RateLimit;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;
use App\Shared\Http\UsesSession;

/**
 * Authentication for the merchant panel over the session cookie. Every state change needs
 * the session's CSRF token, which GET /api/v1/session issues.
 */
final readonly class AuthApiController
{
    private const string SESSION_ROUTE_PATH = '/api/v1/session';
    private const string REGISTRATIONS_ROUTE_PATH = '/api/v1/registrations';
    private const string EMAIL_CONFIRMATIONS_ROUTE_PATH = '/api/v1/email-confirmations';
    private const string PASSWORD_RESETS_ROUTE_PATH = '/api/v1/password-resets';
    private const string PASSWORD_RESET_CONFIRMATIONS_ROUTE_PATH = '/api/v1/password-reset-confirmations';

    public function __construct(
        private RegisterUserService $registerUser,
        private LoginUserService $loginUser,
        private ConfirmEmailService $confirmEmail,
        private RequestPasswordResetService $requestPasswordReset,
        private ResetPasswordService $resetPassword,
        private CurrentUserInterface $currentUser,
        private CsrfToken $csrfToken,
        private Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::GET, self::SESSION_ROUTE_PATH)]
    #[UsesSession]
    public function showSession(): void
    {
        $this->respondWithSession($this->csrfToken->get() ?? $this->csrfToken->create());
    }

    #[Route(HttpMethodEnum::POST, self::SESSION_ROUTE_PATH)]
    #[UsesSession]
    #[RateLimit(AuthRateLimitEnum::SIGN_IN->value)]
    #[MapException(InvalidCredentialsException::class, ResponseStatusEnum::UNAUTHORIZED)]
    #[MapException(EmailNotVerifiedException::class, ResponseStatusEnum::FORBIDDEN)]
    public function signIn(Request $request): void
    {
        $credentials = CredentialsRequest::fromJson($request);
        $this->loginUser->execute($credentials->email, $credentials->password);

        // A token issued before sign-in could have been planted by someone else, so the
        // signed-in session gets a new one.
        $this->respondWithSession($this->csrfToken->create());
    }

    #[Route(HttpMethodEnum::DELETE, self::SESSION_ROUTE_PATH)]
    #[UsesSession]
    public function signOut(): void
    {
        $this->currentUser->signOut();

        $this->responder->withoutBody(ResponseStatusEnum::NO_CONTENT);
    }

    #[Route(HttpMethodEnum::POST, self::REGISTRATIONS_ROUTE_PATH)]
    #[UsesSession]
    #[RateLimit(AuthRateLimitEnum::REGISTRATION->value)]
    #[MapException(InvalidEmailException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    #[MapException(WeakPasswordException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    #[MapException(UnsupportedPasswordException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    #[MapException(ResendLimitReachedException::class, ResponseStatusEnum::TOO_MANY_REQUESTS)]
    public function register(Request $request): void
    {
        $credentials = CredentialsRequest::fromJson($request);
        $this->registerUser->execute($credentials->email, $credentials->password);

        $this->responder->withoutBody(ResponseStatusEnum::ACCEPTED);
    }

    #[Route(HttpMethodEnum::POST, self::EMAIL_CONFIRMATIONS_ROUTE_PATH)]
    #[UsesSession]
    #[MapException(InvalidConfirmationTokenException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    public function confirmEmail(Request $request): void
    {
        $this->confirmEmail->execute(ConfirmationTokenRequest::fromJson($request)->token);

        $this->responder->withoutBody(ResponseStatusEnum::NO_CONTENT);
    }

    #[Route(HttpMethodEnum::POST, self::PASSWORD_RESETS_ROUTE_PATH)]
    #[UsesSession]
    #[RateLimit(AuthRateLimitEnum::PASSWORD_RESET->value)]
    #[MapException(InvalidEmailException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    public function requestPasswordReset(Request $request): void
    {
        $this->requestPasswordReset->execute(ResetLinkRequest::fromJson($request)->email);

        $this->responder->withoutBody(ResponseStatusEnum::ACCEPTED);
    }

    #[Route(HttpMethodEnum::POST, self::PASSWORD_RESET_CONFIRMATIONS_ROUTE_PATH)]
    #[UsesSession]
    #[MapException(InvalidResetTokenException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    #[MapException(WeakPasswordException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    #[MapException(UnsupportedPasswordException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    public function resetPassword(Request $request): void
    {
        $newPassword = NewPasswordRequest::fromJson($request);
        $this->resetPassword->execute($newPassword->token, $newPassword->password);

        $this->responder->withoutBody(ResponseStatusEnum::NO_CONTENT);
    }

    private function respondWithSession(string $csrfToken): void
    {
        $this->responder->json(new SessionResponse($this->currentUser->email(), $this->currentUser->isMerchant(), $csrfToken));
    }
}
