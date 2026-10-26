<?php

declare(strict_types=1);

namespace App\Auth\Service;

final readonly class LoginUserService
{
    public function __construct(
        private UserAuthService $authService,
        private CurrentUserInterface $currentUser,
    ) {
    }

    public function execute(string $email, string $password): void
    {
        $this->authService->authenticate($email, $password)
            |> $this->currentUser->signIn(...);
    }
}
