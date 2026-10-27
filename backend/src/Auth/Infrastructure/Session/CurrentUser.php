<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Session;

use App\Auth\Entity\User;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\CurrentUserInterface;
use App\Shared\Http\Session;
use App\Shared\Http\SessionUserInterface;

/**
 * Keeps the signed-in user in the browser session. Merchant access is read from the account
 * on every request, so granting or revoking it applies at once.
 */
final readonly class CurrentUser implements CurrentUserInterface, SessionUserInterface
{
    private const string SESSION_KEY = 'user_email';

    public function __construct(private Session $session, private UserRepositoryInterface $users)
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

    public function isMerchant(): bool
    {
        $email = $this->email();

        return $email !== null && $this->users->findByEmail($email)?->isMerchant() === true;
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
