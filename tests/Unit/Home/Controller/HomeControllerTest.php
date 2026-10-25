<?php

declare(strict_types=1);

namespace Tests\Unit\Home\Controller;

use App\Auth\Entity\User;
use App\Auth\Service\CurrentUser;
use App\Home\Controller\HomeController;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Responder;
use App\Shared\Http\Session;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    private const string HOME_HEADING = '<h1>PHP MVC Playground</h1>';
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD_HASH = 'password-hash';
    private const string SIGNED_IN_FORMAT = 'Signed in as %s';

    /** @var array<string, mixed> */
    private array $originalSession;
    private Session $session;
    private CurrentUser $currentUser;

    protected function setUp(): void
    {
        $this->session = new Session();
        $this->session->start();
        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $this->currentUser = new CurrentUser($this->session);
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    public function testRendersHomePageForGuest(): void
    {
        $output = $this->render();

        self::assertStringContainsString(self::HOME_HEADING, $output);
        self::assertStringNotContainsString(sprintf(self::SIGNED_IN_FORMAT, self::EMAIL), $output);
    }

    public function testShowsSignedInUser(): void
    {
        $this->currentUser->signIn(new User(self::EMAIL, self::PASSWORD_HASH, new DateTimeImmutable()));

        self::assertStringContainsString(sprintf(self::SIGNED_IN_FORMAT, self::EMAIL), $this->render());
    }

    private function render(): string
    {
        $controller = new HomeController($this->currentUser, new Responder(new CsrfToken($this->session)));
        ob_start();
        $controller->index();
        $output = ob_get_clean();
        self::assertIsString($output);

        return $output;
    }
}
