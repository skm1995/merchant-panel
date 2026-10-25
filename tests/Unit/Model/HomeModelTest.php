<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\HomeModel;
use PHPUnit\Framework\TestCase;

final class HomeModelTest extends TestCase
{
    private const string EXPECTED_MESSAGE = 'PHP MVC playground is running.';

    public function testReturnsTheHomePageMessage(): void
    {
        $homeModel = new HomeModel();

        self::assertSame(self::EXPECTED_MESSAGE, $homeModel->message());
    }
}
