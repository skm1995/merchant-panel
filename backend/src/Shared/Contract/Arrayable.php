<?php

declare(strict_types=1);

namespace App\Shared\Contract;

/**
 * Implemented by DTOs that need an array representation, such as API response bodies.
 */
interface Arrayable
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
