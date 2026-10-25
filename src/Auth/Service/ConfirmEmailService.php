<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Exception\InvalidConfirmationTokenException;
use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Shared\Database\Enum\IsolationLevelEnum;
use App\Shared\Database\TransactionRunnerInterface;
use DateTimeImmutable;

final class ConfirmEmailService
{
    public function __construct(
        private readonly EmailVerificationTokenRepositoryInterface $tokens,
        private readonly TransactionRunnerInterface $transactions,
    ) {
    }

    public function execute(string $token): void
    {
        $confirmed = $this->transactions->run(
            fn (): bool => $this->tokens->confirmEmail(ConfirmationToken::fromString($token)->hash(), new DateTimeImmutable()),
            IsolationLevelEnum::READ_COMMITTED,
        );

        if (!$confirmed) {
            throw new InvalidConfirmationTokenException();
        }
    }
}
