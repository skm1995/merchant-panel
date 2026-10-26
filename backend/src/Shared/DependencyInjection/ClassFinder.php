<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Lists the classes, interfaces, and enums under a directory by mapping file paths to names
 * the PSR-4 way, so attributes can be discovered without a registry to keep in sync.
 */
final class ClassFinder
{
    private const string PHP_EXTENSION = 'php';
    private const string PATH_SEPARATOR = '/';
    private const string NAMESPACE_SEPARATOR = '\\';
    private const string CLASS_NAME_FORMAT = '%s\\%s';

    /** @return list<class-string> */
    public static function find(string $directory, string $namespace): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $classes = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== self::PHP_EXTENSION) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($directory, '/')) + 1, -strlen(self::PHP_EXTENSION) - 1);
            $class = sprintf(
                self::CLASS_NAME_FORMAT,
                $namespace,
                str_replace(self::PATH_SEPARATOR, self::NAMESPACE_SEPARATOR, $relative),
            );

            if (class_exists($class) || interface_exists($class) || enum_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }
}
