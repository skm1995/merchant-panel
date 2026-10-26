<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Config\Environment;
use App\Shared\Http\Enum\ServerParameterEnum;

final class Session
{
    private const int COOKIE_LIFETIME = 0;
    private const string COOKIE_PATH = '/';
    private const string SAME_SITE_POLICY = 'Lax';
    private const string SECURE_COOKIE_ENVIRONMENT_KEY = 'SESSION_COOKIE_SECURE';
    private const string STRICT_MODE_SETTING = 'session.use_strict_mode';
    private const string ENABLED_SETTING = '1';
    private const string HTTPS_DISABLED = 'off';

    public function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        ini_set(self::STRICT_MODE_SETTING, self::ENABLED_SETTING);
        session_set_cookie_params([
            'lifetime' => self::COOKIE_LIFETIME,
            'path' => self::COOKIE_PATH,
            'secure' => self::shouldUseSecureCookie(),
            'httponly' => true,
            'samesite' => self::SAME_SITE_POLICY,
        ]);
        session_start();
    }

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerateId(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    private static function shouldUseSecureCookie(): bool
    {
        $configured = Environment::find(self::SECURE_COOKIE_ENVIRONMENT_KEY);

        $https = $_SERVER[ServerParameterEnum::HTTPS->value] ?? null;

        return is_string($configured)
            ? filter_var($configured, FILTER_VALIDATE_BOOL)
            : is_string($https) && $https !== self::HTTPS_DISABLED;
    }
}
