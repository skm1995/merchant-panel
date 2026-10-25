<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Shared\Config\Environment;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Builds browsers that talk to the application through the configured web server.
 */
final class Browser
{
    private const string APP_BASE_URL_ENVIRONMENT_KEY = 'APP_BASE_URL';
    private const string URL_PATH_SEPARATOR = '/';

    public static function create(): HttpBrowser
    {
        return new HttpBrowser(HttpClient::create());
    }

    public static function url(string $path): string
    {
        return rtrim(Environment::get(self::APP_BASE_URL_ENVIRONMENT_KEY), self::URL_PATH_SEPARATOR) . $path;
    }
}
