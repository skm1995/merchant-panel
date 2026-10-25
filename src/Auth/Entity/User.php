<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public string $email,
        public string $password,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $emailVerifiedAt = null,
    ) {
    }
}
