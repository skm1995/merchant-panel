<?php

declare(strict_types=1);

namespace App\Shared\Messaging;

use Attribute;

/**
 * Marks a method that handles the message type of its single parameter in the worker.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class AsMessageHandler
{
}
