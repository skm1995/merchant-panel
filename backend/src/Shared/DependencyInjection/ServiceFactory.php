<?php

declare(strict_types=1);

namespace App\Shared\DependencyInjection;

use Attribute;

/**
 * Marks a static or instance method that builds the service named by its return type; its
 * parameters are autowired like constructor parameters.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ServiceFactory
{
}
