<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Fixture\Bus;

use DomainException;

final class RenameItemHandler
{
    public const string REFUSED_NAME = 'refused';

    public function handle(RenameItemCommand $command): string
    {
        if ($command->name === self::REFUSED_NAME) {
            throw new DomainException('This name is refused.');
        }

        return 'renamed to ' . $command->name;
    }
}
