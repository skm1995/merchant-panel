<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Fixture\Discovery;

use App\Shared\Messaging\AsMessageHandler;

final class ExampleMessageHandler
{
    /** @var list<object> */
    public array $handled = [];

    #[AsMessageHandler]
    public function handle(ExampleRoutedMessage $message): void
    {
        $this->handled[] = $message;
    }

    public function helper(ExampleDirectMessage $message): void
    {
    }
}
