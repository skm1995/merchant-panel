<?php

declare(strict_types=1);

namespace App\Report\Request;

use App\Order\Enum\OrderStatusEnum;
use App\Report\Query\OrderReportQuery;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use DateTimeImmutable;

/**
 * Accepts the parameters of the legacy order report URL, but only known statuses and real dates.
 */
final readonly class OrderReportRequest
{
    private const string STATUS_PARAMETER = 'status';
    private const string SINCE_PARAMETER = 'since';
    private const string DEFAULT_SINCE = '2000-01-01';
    private const string DATE_FORMAT = '!Y-m-d';
    private const string OUTPUT_DATE_FORMAT = 'Y-m-d';
    private const string INVALID_STATUS_MESSAGE = 'The "status" parameter must be a known order status.';
    private const string INVALID_SINCE_MESSAGE = 'The "since" parameter must be a date in YYYY-MM-DD format.';

    public function __construct(public OrderStatusEnum $status, public DateTimeImmutable $since)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $status = OrderStatusEnum::tryFrom($request->query(self::STATUS_PARAMETER) ?? OrderStatusEnum::PAID->value)
            ?? throw new InvalidRequestException(self::INVALID_STATUS_MESSAGE);
        $sinceValue = $request->query(self::SINCE_PARAMETER) ?? self::DEFAULT_SINCE;
        $since = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $sinceValue);

        if ($since === false || $since->format(self::OUTPUT_DATE_FORMAT) !== $sinceValue) {
            throw new InvalidRequestException(self::INVALID_SINCE_MESSAGE);
        }

        return new self($status, $since);
    }

    public function toQuery(): OrderReportQuery
    {
        return new OrderReportQuery($this->status, $this->since);
    }
}
