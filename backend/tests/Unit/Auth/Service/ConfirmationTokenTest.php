<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Service\ConfirmationToken;
use PHPUnit\Framework\TestCase;

final class ConfirmationTokenTest extends TestCase
{
    private const string TOKEN = 'confirmation-token';
    private const string TOKEN_HASH = '23a0f8a5d44eb66f9f082c737258aaf003ccf023127f695078f39fd7f57cd2e6';
    private const string HEX_TOKEN_PATTERN = '/^[a-f0-9]{64}$/';

    public function testGeneratesUniqueHexadecimalTokens(): void
    {
        $first = ConfirmationToken::generate();
        $second = ConfirmationToken::generate();

        self::assertMatchesRegularExpression(self::HEX_TOKEN_PATTERN, $first->value);
        self::assertNotSame($first->value, $second->value);
    }

    public function testHashesTheTokenValueWithSha256(): void
    {
        self::assertSame(self::TOKEN_HASH, ConfirmationToken::fromString(self::TOKEN)->hash());
    }
}
