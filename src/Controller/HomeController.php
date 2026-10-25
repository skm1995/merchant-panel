<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Model\HomeModelInterface;

final class HomeController extends Controller
{
    public const string ROUTE_PATH = '/';
    private const string TEMPLATE = 'home/index';
    private const string MESSAGE_DATA_KEY = 'message';

    public function __construct(private readonly HomeModelInterface $homeModel)
    {
    }

    public function index(): void
    {
        $this->render(self::TEMPLATE, [self::MESSAGE_DATA_KEY => $this->homeModel->message()]);
    }
}
