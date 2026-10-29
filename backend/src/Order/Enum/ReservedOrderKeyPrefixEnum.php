<?php

declare(strict_types=1);

namespace App\Order\Enum;

/**
 * Idempotency-key prefixes of the orders the shop places itself: the generated sales history
 * and the Black Friday simulations. The shop finds and removes those orders by these
 * prefixes, so checkout refuses them from customers.
 */
enum ReservedOrderKeyPrefixEnum: string
{
    case HISTORY = 'history:';
    case SIMULATION = 'simulation:';

    private const string ANY_REST = '%';

    public static function of(string $idempotencyKey): ?self
    {
        foreach (self::cases() as $prefix) {
            if (str_starts_with($idempotencyKey, $prefix->value)) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * The LIKE pattern of every key with this prefix; it uses the unique index on the key.
     */
    public function likePattern(): string
    {
        return $this->value . self::ANY_REST;
    }
}
