<?php

declare(strict_types=1);

namespace App\Shared\Dto;

use App\Shared\Contract\Arrayable;
use Money\Money;

/**
 * API representation of money: the amount in minor units as a string plus the ISO 4217 code.
 */
final readonly class MoneyResponse implements Arrayable
{
    private const string AMOUNT_KEY = 'amount';
    private const string CURRENCY_KEY = 'currency';

    public function __construct(public string $amount, public string $currency)
    {
    }

    public static function fromMoney(Money $money): self
    {
        return new self($money->getAmount(), $money->getCurrency()->getCode());
    }

    public function toArray(): array
    {
        return [self::AMOUNT_KEY => $this->amount, self::CURRENCY_KEY => $this->currency];
    }
}
