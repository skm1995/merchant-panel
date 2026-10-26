<?php

declare(strict_types=1);

namespace App\Report\Exception;

use DomainException;

final class InvalidReportPeriodException extends DomainException
{
    private const string MESSAGE_FORMAT = 'A report period must start before it ends and cover at most %d days.';

    public function __construct(int $maximumDays)
    {
        parent::__construct(sprintf(self::MESSAGE_FORMAT, $maximumDays));
    }
}
