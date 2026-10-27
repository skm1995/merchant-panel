<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Entity\User;

/**
 * Who is signed in. Use cases depend on this port; the HTTP session adapter implements it,
 * so application services stay unaware of sessions and cookies.
 */
interface CurrentUserInterface
{
    public function email(): ?string;

    /**
     * Whether the signed-in account may use the merchant panel, as stored now.
     */
    public function isMerchant(): bool;

    public function signIn(User $user): void;

    public function signOut(): void;
}
