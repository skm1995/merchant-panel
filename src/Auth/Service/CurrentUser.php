<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\User;
use App\Shared\Http\Session;

final class CurrentUser
{
    private const string SESSION_KEY = 'user_email';

    public function __construct(private readonly Session $session)
    {
    }

    public function email(): ?string
    {
        $email = $this->session->get(self::SESSION_KEY);

        return is_string($email) ? $email : null;
    }

    public function signIn(User $user): void
    {
        $this->session->regenerateId();
        $this->session->set(self::SESSION_KEY, $user->email);
    }

    public function signOut(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->regenerateId();
    }
}
