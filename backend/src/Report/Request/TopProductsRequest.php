<?php

declare(strict_types=1);

namespace App\Report\Request;

use App\Report\Service\ReportPeriod;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

final readonly class TopProductsRequest
{
    public const int DEFAULT_LIMIT = 10;
    public const int MAXIMUM_LIMIT = 50;
    private const string LIMIT_PARAMETER = 'limit';
    private const string INVALID_LIMIT_FORMAT = 'The "limit" query parameter must be an integer from 1 to %d.';
    private const int MINIMUM_LIMIT = 1;

    public function __construct(public ReportPeriod $period, public int $limit)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $period = ReportPeriodInput::parse($request);
        $limit = $request->query(self::LIMIT_PARAMETER);

        if ($limit === null) {
            return new self($period, self::DEFAULT_LIMIT);
        }

        $parsed = filter_var($limit, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::MINIMUM_LIMIT, 'max_range' => self::MAXIMUM_LIMIT],
        ]);

        return $parsed !== false
            ? new self($period, $parsed)
            : throw new InvalidRequestException(sprintf(self::INVALID_LIMIT_FORMAT, self::MAXIMUM_LIMIT));
    }
}
