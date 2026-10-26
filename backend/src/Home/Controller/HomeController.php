<?php

declare(strict_types=1);

namespace App\Home\Controller;

use App\Auth\Service\CurrentUser;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;

final readonly class HomeController
{
    private const string TEMPLATE = 'home/index';
    private const string USER_EMAIL_DATA_KEY = 'userEmail';

    public function __construct(
        private CurrentUser $currentUser,
        private Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::GET, Request::ROOT_PATH)]
    public function index(): void
    {
        $this->responder->render(self::TEMPLATE, [self::USER_EMAIL_DATA_KEY => $this->currentUser->email()]);
    }
}
