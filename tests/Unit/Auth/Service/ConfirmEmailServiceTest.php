<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Exception\InvalidConfirmationTokenException;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Auth\Service\ConfirmEmailService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ConfirmEmailServiceTest extends TestCase
{
    private const string TOKEN = 'confirmation-token';
    private const string TOKEN_HASH = '23a0f8a5d44eb66f9f082c737258aaf003ccf023127f695078f39fd7f57cd2e6';

    public function testValidTokenConfirmsEmail(): void
    {
        $tokens = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $tokens->expects(self::once())->method('confirmEmail')->with(
            self::TOKEN_HASH,
            self::isInstanceOf(DateTimeImmutable::class),
        )->willReturn(true);

        new ConfirmEmailService($tokens)->execute(self::TOKEN);
    }

    public function testInvalidOrExpiredTokenIsRejected(): void
    {
        $tokens = $this->createStub(EmailVerificationTokenRepositoryInterface::class);
        $tokens->method('confirmEmail')->willReturn(false);

        $this->expectException(InvalidConfirmationTokenException::class);
        new ConfirmEmailService($tokens)->execute(self::TOKEN);
    }
}
