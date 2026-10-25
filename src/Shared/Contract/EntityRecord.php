<?php

declare(strict_types=1);

namespace App\Shared\Contract;

/**
 * Implemented by hydrated database records that represent one domain entity.
 *
 * @template TEntity of object
 */
interface EntityRecord
{
    /** @return TEntity */
    public function toEntity(): object;
}
