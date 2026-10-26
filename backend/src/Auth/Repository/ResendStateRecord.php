<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Shared\Database\HydratedRecord;
use DateTimeImmutable;

#[HydratedRecord]
final readonly class ResendStateRecord
{
    public int $resendCount;
    public string $resendWindowStartedAt;

    public function windowStartedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->resendWindowStartedAt);
    }
}
