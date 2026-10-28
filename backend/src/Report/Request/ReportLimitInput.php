<?php

declare(strict_types=1);

namespace App\Report\Request;

use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

/**
 * Parses the limit query parameter of the reports that list products.
 */
final class ReportLimitInput
{
    public const int DEFAULT_LIMIT = 10;
    public const int MAXIMUM_LIMIT = 50;
    private const string LIMIT_PARAMETER = 'limit';
    private const string INVALID_LIMIT_FORMAT = 'The "limit" query parameter must be an integer from 1 to %d.';
    private const int MINIMUM_LIMIT = 1;

    public static function parse(Request $request): int
    {
        $limit = $request->query(self::LIMIT_PARAMETER);

        if ($limit === null) {
            return self::DEFAULT_LIMIT;
        }

        $parsed = filter_var($limit, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::MINIMUM_LIMIT, 'max_range' => self::MAXIMUM_LIMIT],
        ]);

        return $parsed !== false
            ? $parsed
            : throw new InvalidRequestException(sprintf(self::INVALID_LIMIT_FORMAT, self::MAXIMUM_LIMIT));
    }
}
