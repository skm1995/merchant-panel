<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Auth\Service\ConfirmEmailService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ConfirmEmailServiceTest extends TestCase
{
    private const string TOKEN = 'confirmation-token';
    private const string TOKEN_HASH = '23a0f8a5d44eb66f9f082c737258aaf003ccf023127f695078f39fd7f57cd2e6';
    private const string CONFIRMED_PATH = '/email-confirmed';
    private const string INVALID_TOKEN_MESSAGE = 'This confirmation link is invalid or has expired.';

    public function testValidTokenReturnsConfirmationPageRedirect(): void
    {
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('verify')->with(
            self::TOKEN_HASH,
            self::isInstanceOf(DateTimeImmutable::class),
        )->willReturn(true);

        $result = new ConfirmEmailService($tokens)->execute(self::TOKEN);

        self::assertSame(self::CONFIRMED_PATH, $result->redirectPath);
    }

    public function testInvalidOrExpiredTokenReturnsAnError(): void
    {
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('verify')->willReturn(false);

        $result = new ConfirmEmailService($tokens)->execute(self::TOKEN);

        self::assertSame(self::INVALID_TOKEN_MESSAGE, $result->errorMessage);
        self::assertNull($result->email);
    }
}
