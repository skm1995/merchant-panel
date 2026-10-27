<?php

declare(strict_types=1);

namespace App\Auth\Response;

use App\Shared\Contract\Arrayable;

/**
 * The browser session as the panel sees it: who is signed in, whether they may use the
 * merchant panel, and the CSRF token to send with state-changing requests.
 */
final readonly class SessionResponse implements Arrayable
{
    private const string AUTHENTICATED_KEY = 'authenticated';
    private const string EMAIL_KEY = 'email';
    private const string MERCHANT_KEY = 'merchant';
    private const string CSRF_TOKEN_KEY = 'csrf_token';

    public function __construct(public ?string $email, public bool $merchant, public string $csrfToken)
    {
    }

    public function toArray(): array
    {
        return [
            self::AUTHENTICATED_KEY => $this->email !== null,
            self::EMAIL_KEY => $this->email,
            self::MERCHANT_KEY => $this->merchant,
            self::CSRF_TOKEN_KEY => $this->csrfToken,
        ];
    }
}
