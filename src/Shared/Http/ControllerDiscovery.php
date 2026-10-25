<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\DependencyInjection\ClassFinder;
use ReflectionClass;

/**
 * Finds controllers: instantiable classes in the features' Controller directories with at
 * least one #[Route] method.
 */
final class ControllerDiscovery
{
    private const string CONTROLLER_DIRECTORIES = '%s/src/*/Controller';
    private const string SOURCE_DIRECTORY = '%s/src';
    private const string ROOT_NAMESPACE = 'App';
    private const string NAMESPACE_FORMAT = '%s\\%s';

    /** @return list<class-string> */
    public static function fromSource(): array
    {
        $root = dirname(__DIR__, 3);
        $classes = [];

        foreach (glob(sprintf(self::CONTROLLER_DIRECTORIES, $root)) ?: [] as $directory) {
            $relative = substr($directory, strlen(sprintf(self::SOURCE_DIRECTORY, $root)) + 1);
            $namespace = sprintf(self::NAMESPACE_FORMAT, self::ROOT_NAMESPACE, str_replace('/', '\\', $relative));
            $classes = [...$classes, ...ClassFinder::find($directory, $namespace)];
        }

        return self::controllers($classes);
    }

    /**
     * @param list<class-string> $classes
     * @return list<class-string>
     */
    public static function controllers(array $classes): array
    {
        return array_values(array_filter($classes, static function (string $class): bool {
            $reflection = new ReflectionClass($class);

            foreach ($reflection->getMethods() as $method) {
                if ($method->getAttributes(Route::class) !== []) {
                    return $reflection->isInstantiable();
                }
            }

            return false;
        }));
    }
}
