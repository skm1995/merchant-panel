<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DependencyInjection\Fixture\Services;

interface ChannelInterface
{
    public function runtime(): Runtime;
}
