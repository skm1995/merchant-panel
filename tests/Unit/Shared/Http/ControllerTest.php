<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\Controller;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;

final class ControllerTest extends TestCase
{
    private const string MESSAGE = '<unsafe message>';
    private const string ESCAPED_MESSAGE = '&lt;unsafe message&gt;';

    public function testRenderLoadsViewAndEscapesDynamicValues(): void
    {
        $session = new Session();
        $controller = new class (new CsrfToken($session)) extends Controller {
            private const string VIEW = 'auth/confirmation-error';
            private const string MESSAGE_KEY = 'error';

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
