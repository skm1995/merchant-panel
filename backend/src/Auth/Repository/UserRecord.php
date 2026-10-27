<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\User;
use App\Shared\Contract\EntityRecord;
use App\Shared\Database\HydratedRecord;
use DateTimeImmutable;

/** @implements EntityRecord<User> */
#[HydratedRecord]
final readonly class UserRecord implements EntityRecord
{
    public string $email;
    public string $passwordHash;
    public string $createdAt;
    public ?string $emailVerifiedAt;
    public ?string $merchantSince;

    public function toEntity(): User
    {
        return new User(
            $this->email,
            $this->passwordHash,
            new DateTimeImmutable($this->createdAt),
            $this->emailVerifiedAt === null ? null : new DateTimeImmutable($this->emailVerifiedAt),
            $this->merchantSince === null ? null : new DateTimeImmutable($this->merchantSince),
        );
    }
}
