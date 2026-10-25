<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\User;
use App\Shared\Database\HydratedRecord;
use DateTimeImmutable;

#[HydratedRecord]
final readonly class UserRecord
{
    public string $email;
    public string $passwordHash;
    public string $createdAt;
    public ?string $emailVerifiedAt;

    public function toUser(): User
    {
        return new User(
            $this->email,
            $this->passwordHash,
            new DateTimeImmutable($this->createdAt),
            $this->emailVerifiedAt === null ? null : new DateTimeImmutable($this->emailVerifiedAt),
        );
    }
}
