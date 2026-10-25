<?php

declare(strict_types=1);

namespace App\Catalog\Dto;

use App\Catalog\Service\ProductCatalog;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

final readonly class ListProductsQuery
{
    private const string LIMIT_PARAMETER = 'limit';
    private const string CURSOR_PARAMETER = 'cursor';
    private const string INVALID_PARAMETER_FORMAT = 'The "%s" query parameter must be a positive integer.';
    private const int MINIMUM_POSITIVE_INTEGER = 1;

    public function __construct(public int $limit, public ?int $cursor)
    {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            self::positiveInteger($request, self::LIMIT_PARAMETER) ?? ProductCatalog::DEFAULT_PAGE_SIZE,
            self::positiveInteger($request, self::CURSOR_PARAMETER),
        );
    }

    private static function positiveInteger(Request $request, string $parameter): ?int
    {
        $value = $request->query($parameter);

        if ($value === null) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::MINIMUM_POSITIVE_INTEGER],
        ]);

        return $integer !== false
            ? $integer
            : throw new InvalidRequestException(sprintf(self::INVALID_PARAMETER_FORMAT, $parameter));
    }
}
