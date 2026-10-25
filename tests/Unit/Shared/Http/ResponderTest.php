<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Responder;
use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;

final class ResponderTest extends TestCase
{
    private const string VIEW = 'auth/login';
    private const string ERROR_KEY = 'error';
    private const string MESSAGE = '<unsafe message>';
    private const string ESCAPED_MESSAGE = '&lt;unsafe message&gt;';
    private const string CSRF_FIELD_FORMAT = 'name="%s" value="%s"';
    private const string REDIRECT_PATH = '/login';
    private const string BAD_REQUEST_BODY = '400 Bad Request';

    /** @var array<string, mixed> */
    private array $originalSession;
    private CsrfToken $csrfToken;
    private Responder $responder;

    protected function setUp(): void
    {
        $session = new Session();
        $session->start();
        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $this->csrfToken = new CsrfToken($session);
        $this->responder = new Responder($this->csrfToken);
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testRenderEscapesDynamicValuesAndAddsCsrfField(): void
    {
        ob_start();

        $this->responder->render(self::VIEW, [self::ERROR_KEY => self::MESSAGE]);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString(self::ESCAPED_MESSAGE, $output);
        self::assertStringContainsString(
            sprintf(self::CSRF_FIELD_FORMAT, CsrfToken::FIELD_NAME, $this->csrfToken->get()),
            $output,
        );
    }

    public function testRedirectUsesSeeOtherStatus(): void
    {
        $this->responder->redirect(self::REDIRECT_PATH);

        self::assertSame(ResponseStatusEnum::SEE_OTHER->value, http_response_code());
    }

    public function testStatusWritesPlainStatusLine(): void
    {
        ob_start();

        $this->responder->status(ResponseStatusEnum::BAD_REQUEST);
        $output = ob_get_clean();

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, http_response_code());
        self::assertSame(self::BAD_REQUEST_BODY, $output);
    }
}
