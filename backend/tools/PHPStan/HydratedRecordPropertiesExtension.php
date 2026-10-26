<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use App\Shared\Database\HydratedRecord;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;

/**
 * Treats properties of #[HydratedRecord] classes as initialized, because PDO writes
 * them while hydrating query results.
 */
final class HydratedRecordPropertiesExtension implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
    {
        return false;
    }

    public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
    {
        return self::isHydrated($property);
    }

    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return self::isHydrated($property);
    }

    private static function isHydrated(PropertyReflection $property): bool
    {
        return $property->getDeclaringClass()->getNativeReflection()->getAttributes(HydratedRecord::class) !== [];
    }
}
