<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Auth\Repository\PdoUserRepository;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;
use Tests\Functional\Support\Browser;
use Tests\Functional\Support\TestUserData;

final class RoutingTest extends TestCase
{
    private const string HOME_PATH = '/';
    private const string REGISTER_PATH = '/register';
    private const string LOGIN_PATH = '/login';
    private const string CONFIRM_PATH = '/confirm';
    private const string MISSING_PATH = '/missing-page';
    private const string API_PATH = '/api/v1/products';
    private const string HEADING_SELECTOR = 'h1';
    private const string SESSION_COOKIE = 'PHPSESSID';
    private const string LAX_SAME_SITE = 'lax';
    private const string CSRF_EMAIL_PREFIX = 'csrf-';
    private const string PASSWORD = 'functional-password-123';

    private HttpBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = Browser::create();
    }

    /** @return iterable<string, array{string, string}> */
    public static function pages(): iterable
    {
        yield 'home' => [self::HOME_PATH, 'PHP MVC Playground'];
        yield 'register' => [self::REGISTER_PATH, 'Create account'];
        yield 'login' => [self::LOGIN_PATH, 'Log in'];
        yield 'confirmation sent' => ['/confirmation-sent', 'Check your email'];
        yield 'email confirmed' => ['/email-confirmed', 'Email confirmed'];
    }

    #[DataProvider('pages')]
    public function testWebServerRoutesPageToFrontController(string $path, string $heading): void
    {
        $page = $this->browser->request(HttpMethodEnum::GET->value, Browser::url($path));

        self::assertSame(ResponseStatusEnum::OK->value, $this->browser->getInternalResponse()->getStatusCode());
        self::assertSame($heading, $page->filter(self::HEADING_SELECTOR)->text());
    }

    public function testUnknownPathIsNotFound(): void
    {
        $this->browser->request(HttpMethodEnum::GET->value, Browser::url(self::MISSING_PATH));

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $this->browser->getInternalResponse()->getStatusCode());
    }

    public function testSessionCookieIsHttpOnlyAndSameSite(): void
    {
        $this->browser->request(HttpMethodEnum::GET->value, Browser::url(self::LOGIN_PATH));
        $cookie = $this->browser->getCookieJar()->get(self::SESSION_COOKIE);

        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(self::LAX_SAME_SITE, strtolower((string) $cookie->getSameSite()));
    }

    public function testApiRequestsDoNotStartASession(): void
    {
        $this->browser->request(HttpMethodEnum::GET->value, Browser::url(self::API_PATH));

        self::assertSame(ResponseStatusEnum::OK->value, $this->browser->getInternalResponse()->getStatusCode());
        self::assertNull($this->browser->getCookieJar()->get(self::SESSION_COOKIE));
    }

    public function testConfirmationPageWithoutTokenIsBadRequest(): void
    {
        $this->browser->request(HttpMethodEnum::GET->value, Browser::url(self::CONFIRM_PATH));

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $this->browser->getInternalResponse()->getStatusCode());
    }

    public function testPostWithoutCsrfTokenIsForbiddenAndCreatesNoAccount(): void
    {
        $email = TestUserData::uniqueEmail(self::CSRF_EMAIL_PREFIX);
        $pdo = DatabaseConnection::createFromEnvironment();

        try {
            $this->browser->request(HttpMethodEnum::POST->value, Browser::url(self::REGISTER_PATH), [
                'email' => $email,
                'password' => self::PASSWORD,
            ]);

            self::assertSame(ResponseStatusEnum::FORBIDDEN->value, $this->browser->getInternalResponse()->getStatusCode());
            self::assertNull(new PdoUserRepository($pdo)->findByEmail($email));
        } finally {
            TestUserData::delete($pdo, $email);
        }
    }
}
