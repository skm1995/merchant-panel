<?php

declare(strict_types=1);

namespace App\Auth\Service;

final class LoginUserService
{
    public function __construct(
        private readonly UserAuthService $authService,
        private readonly CurrentUser $currentUser,
    ) {
    }

    public function execute(string $email, string $password): void
    {
        $this->authService->authenticate($email, $password)
            |> $this->currentUser->signIn(...);
    }
}
