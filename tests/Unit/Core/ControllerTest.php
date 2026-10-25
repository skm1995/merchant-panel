<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Controller;
use PHPUnit\Framework\TestCase;

final class ControllerTest extends TestCase
{
    private const string MESSAGE = '<unsafe message>';
    private const string ESCAPED_MESSAGE = '&lt;unsafe message&gt;';

    public function testRenderLoadsViewAndEscapesDynamicValues(): void
    {
        $controller = new class () extends Controller {
            private const string VIEW = 'home/index';
            private const string MESSAGE_KEY = 'message';

            public function renderHome(string $message): void
            {
                $this->render(self::VIEW, [self::MESSAGE_KEY => $message]);
            }
        };
        ob_start();

        $controller->renderHome(self::MESSAGE);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString(self::ESCAPED_MESSAGE, $output);
    }
}
