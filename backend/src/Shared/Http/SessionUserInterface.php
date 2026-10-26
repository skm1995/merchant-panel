<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Whether someone is signed in to the browser session. The Auth feature implements it, so
 * shared HTTP policy can protect routes without depending on that feature.
 */
interface SessionUserInterface
{
    public function isSignedIn(): bool;
}
