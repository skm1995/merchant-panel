<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Session;

use App\Auth\Entity\User;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\CurrentUserInterface;
use App\Shared\Http\Session;
use App\Shared\Http\SessionUserInterface;

/**
 * Keeps the signed-in user in the browser session. The account is read on every request, so
 * granting or revoking merchant access applies at once, and the session also keeps the
 * account's password stamp from sign-in, so changing the password ends every older session.
 */
final readonly class CurrentUser implements CurrentUserInterface, SessionUserInterface
{
    private const string EMAIL_KEY = 'user_email';
    private const string PASSWORD_STAMP_KEY = 'password_stamp';

    public function __construct(private Session $session, private UserRepositoryInterface $users)
    {
    }

    public function email(): ?string
    {
        return $this->signedInUser()?->email;
    }

    public function isSignedIn(): bool
    {
        return $this->signedInUser() !== null;
    }

    public function isMerchant(): bool
    {
        return $this->signedInUser()?->isMerchant() === true;
    }

    public function signIn(User $user): void
    {
        $this->session->regenerateId();
        $this->session->set(self::EMAIL_KEY, $user->email);
        $this->session->set(self::PASSWORD_STAMP_KEY, $user->passwordStamp());
    }

    public function signOut(): void
    {
        $this->session->remove(self::EMAIL_KEY);
        $this->session->remove(self::PASSWORD_STAMP_KEY);
        $this->session->regenerateId();
    }

    /**
     * The account the session signed in to, unless it is gone or its password changed since.
     */
    private function signedInUser(): ?User
    {
        $email = $this->session->get(self::EMAIL_KEY);
        $stamp = $this->session->get(self::PASSWORD_STAMP_KEY);

        if (!is_string($email) || !is_string($stamp)) {
            return null;
        }

        $user = $this->users->findByEmail($email);

        return $user !== null && hash_equals($user->passwordStamp(), $stamp) ? $user : null;
    }
}
