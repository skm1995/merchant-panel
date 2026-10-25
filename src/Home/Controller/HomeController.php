<?php

declare(strict_types=1);

namespace App\Home\Controller;

use App\Shared\Http\Controller;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Route;
use App\Shared\Http\Session;

final class HomeController extends Controller
{
    public const string ROUTE_PATH = '/';
    private const string TEMPLATE = 'home/index';
    private const string USER_EMAIL_SESSION_KEY = 'user_email';
    private const string USER_EMAIL_DATA_KEY = 'userEmail';

    public function __construct(
        private readonly Session $session,
        CsrfToken $csrfToken,
    ) {
        parent::__construct($csrfToken);
    }

    #[Route(HttpMethodEnum::GET, self::ROUTE_PATH)]
    public function index(): void
    {
        $userEmail = $this->session->get(self::USER_EMAIL_SESSION_KEY);
        $this->render(self::TEMPLATE, [
            self::USER_EMAIL_DATA_KEY => is_string($userEmail) ? $userEmail : null,
        ]);
    }
}
