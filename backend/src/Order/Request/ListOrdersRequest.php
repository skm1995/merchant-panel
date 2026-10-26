<?php

declare(strict_types=1);

namespace App\Order\Request;

use App\Order\Entity\OrderId;
use App\Order\Query\ListOrdersQuery;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use InvalidArgumentException;

final class ListOrdersRequest
{
    public const int DEFAULT_LIMIT = 20;
    public const int MAXIMUM_LIMIT = 100;
    private const int MINIMUM_LIMIT = 1;
    private const string LIMIT_PARAMETER = 'limit';
    private const string CURSOR_PARAMETER = 'cursor';
    private const string INVALID_LIMIT_FORMAT = 'The "limit" query parameter must be an integer from 1 to %d.';
    private const string INVALID_CURSOR_MESSAGE = 'The "cursor" query parameter must be the next_cursor of a previous page.';

    public static function toQuery(Request $request): ListOrdersQuery
    {
        return new ListOrdersQuery(self::limit($request), self::cursor($request));
    }

    private static function limit(Request $request): int
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

    private static function cursor(Request $request): ?OrderId
    {
        $cursor = $request->query(self::CURSOR_PARAMETER);

        try {
            return $cursor === null ? null : OrderId::fromString($cursor);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidRequestException(self::INVALID_CURSOR_MESSAGE, previous: $exception);
        }
    }
}
