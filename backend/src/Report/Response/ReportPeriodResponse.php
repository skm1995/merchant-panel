<?php

declare(strict_types=1);

namespace App\Report\Response;

use App\Report\ValueObject\ReportDate;
use App\Report\ValueObject\ReportPeriod;
use App\Shared\Contract\Arrayable;

/**
 * The period and currency every report response starts with.
 */
final readonly class ReportPeriodResponse implements Arrayable
{
    private const string FROM_KEY = 'from';
    private const string TO_KEY = 'to';
    private const string CURRENCY_KEY = 'currency';

    public function __construct(public string $from, public string $to, public string $currency)
    {
    }

    public static function fromPeriod(ReportPeriod $period): self
    {
        return new self(ReportDate::format($period->from), ReportDate::format($period->to), $period->currency->getCode());
    }

    public function toArray(): array
    {
        return [self::FROM_KEY => $this->from, self::TO_KEY => $this->to, self::CURRENCY_KEY => $this->currency];
    }
}
