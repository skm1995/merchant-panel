<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function create(string $email, string $passwordHash): User;
}
