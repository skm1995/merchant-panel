<?php

declare(strict_types=1);

namespace App\Shared\Http;

final class Session
{
    private const int COOKIE_LIFETIME = 0;
    private const string COOKIE_PATH = '/';
    private const string SAME_SITE_POLICY = 'Lax';
    private const string SECURE_COOKIE_ENVIRONMENT_KEY = 'SESSION_COOKIE_SECURE';

    public function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => self::COOKIE_LIFETIME,
            'path' => self::COOKIE_PATH,
            'secure' => self::secureCookie(),
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

    private static function secureCookie(): bool
    {
        $configured = getenv(self::SECURE_COOKIE_ENVIRONMENT_KEY);

        return is_string($configured)
            ? filter_var($configured, FILTER_VALIDATE_BOOL)
            : isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
