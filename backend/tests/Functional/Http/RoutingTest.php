<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\Browser;

/**
 * The reverse proxy serves the React app for every page and sends /api/ and /legacy/ to PHP.
 */
final class RoutingTest extends TestCase
{
    private const string SESSION_PATH = '/api/v1/session';
    private const string PRODUCTS_PATH = '/api/v1/products';
    private const string MISSING_API_PATH = '/api/v1/missing';
    private const string SESSION_COOKIE = 'PHPSESSID';
    private const string LAX_SAME_SITE = 'lax';
    private const string APP_ROOT_ELEMENT = '<div id="root"></div>';
    private const string REQUEST_ID_HEADER = 'X-Request-Id';
    private const string CLIENT_REQUEST_ID = 'functional-test-request-1';
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private HttpBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = Browser::create();
    }

    /** @return iterable<string, array{string}> */
    public static function pages(): iterable
    {
        yield 'home' => ['/'];
        yield 'sign in' => ['/login'];
        yield 'confirmation link' => ['/confirm?token=abc'];
        yield 'panel page' => ['/orders'];
    }

    #[DataProvider('pages')]
    public function testServesTheReactAppForEveryPage(string $path): void
    {
        $this->browser->request(HttpMethodEnum::GET->value, Browser::url($path));

        self::assertSame(ResponseStatusEnum::OK->value, $this->browser->getInternalResponse()->getStatusCode());
        self::assertStringContainsString(self::APP_ROOT_ELEMENT, $this->browser->getInternalResponse()->getContent());
    }

    public function testUnknownApiPathIsANotFoundProblem(): void
    {
        $response = ApiClient::get(self::MISSING_API_PATH);

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $response->getStatusCode());
        self::assertSame('Not Found', ApiClient::json($response)['title'] ?? null);
    }

    public function testSessionCookieIsHttpOnlyAndSameSite(): void
    {
        $this->browser->request(HttpMethodEnum::GET->value, Browser::url(self::SESSION_PATH));
        $cookie = $this->browser->getCookieJar()->get(self::SESSION_COOKIE);

        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(self::LAX_SAME_SITE, strtolower((string) $cookie->getSameSite()));
    }

    public function testPublicApiRequestsDoNotStartASession(): void
    {
        $this->browser->request(HttpMethodEnum::GET->value, Browser::url(self::PRODUCTS_PATH));

        self::assertSame(ResponseStatusEnum::OK->value, $this->browser->getInternalResponse()->getStatusCode());
        self::assertNull($this->browser->getCookieJar()->get(self::SESSION_COOKIE));
    }

    public function testTagsEveryResponseWithItsRequestId(): void
    {
        $generated = ApiClient::get(self::PRODUCTS_PATH);
        $echoed = ApiClient::sendWithQuery(self::PRODUCTS_PATH, [], [self::REQUEST_ID_HEADER => self::CLIENT_REQUEST_ID]);

        self::assertMatchesRegularExpression(self::UUID_PATTERN, $generated->getHeaderLine(self::REQUEST_ID_HEADER));
        self::assertSame(self::CLIENT_REQUEST_ID, $echoed->getHeaderLine(self::REQUEST_ID_HEADER));
    }
}
