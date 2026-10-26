<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\User;
use App\Shared\Http\Session;
use App\Shared\Http\SessionUserInterface;

final readonly class CurrentUser implements SessionUserInterface
{
    private const string SESSION_KEY = 'user_email';

    public function __construct(private Session $session)
    {
    }

    public function email(): ?string
    {
        $email = $this->session->get(self::SESSION_KEY);

        return is_string($email) ? $email : null;
    }

    public function isSignedIn(): bool
    {
        return $this->email() !== null;
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
