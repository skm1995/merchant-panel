<?php

declare(strict_types=1);

namespace App\Catalog\Request;

use App\Shared\Http\EntityTag;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Exception\PreconditionRequiredException;
use App\Shared\Http\Request;
use Money\Currency;
use Money\Money;

final readonly class EditProductRequest
{
    private const string ID_PARAMETER = 'id';
    private const string PRICE_FIELD = 'price';
    private const string AMOUNT_FIELD = 'amount';
    private const string CURRENCY_FIELD = 'currency';
    private const string STOCK_FIELD = 'stock';
    private const string AMOUNT_PATTERN = '/^\d{1,18}$/';
    private const string CURRENCY_PATTERN = '/^[A-Z]{3}$/';
    private const int MINIMUM_STOCK = 0;
    private const string INVALID_PRICE_MESSAGE = 'The "price" field must be money with a non-negative amount in minor units and an ISO 4217 currency.';
    private const string INVALID_STOCK_MESSAGE = 'The "stock" field must be a non-negative integer.';
    private const string MISSING_PRECONDITION_MESSAGE = 'Send the product ETag in the If-Match header to edit it.';

    public function __construct(public int $productId, public Money $price, public int $stock, public int $expectedVersion)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $ifMatch = $request->header(HeaderNameEnum::IF_MATCH->value)
            ?? throw new PreconditionRequiredException(self::MISSING_PRECONDITION_MESSAGE);
        $expectedVersion = EntityTag::fromHeader($ifMatch)->version;
        $data = $request->json();
        $stock = $data[self::STOCK_FIELD] ?? null;

        if (!is_int($stock) || $stock < self::MINIMUM_STOCK) {
            throw new InvalidRequestException(self::INVALID_STOCK_MESSAGE);
        }

        return new self((int) $request->routeParameter(self::ID_PARAMETER), self::price($data[self::PRICE_FIELD] ?? null), $stock, $expectedVersion);
    }

    private static function price(mixed $price): Money
    {
        $amount = is_array($price) ? ($price[self::AMOUNT_FIELD] ?? null) : null;
        $currency = is_array($price) ? ($price[self::CURRENCY_FIELD] ?? null) : null;

        if (
            !is_string($amount)
            || !is_numeric($amount)
            || !is_string($currency)
            || preg_match(self::AMOUNT_PATTERN, $amount) !== 1
            || preg_match(self::CURRENCY_PATTERN, $currency) !== 1
        ) {
            throw new InvalidRequestException(self::INVALID_PRICE_MESSAGE);
        }

        return new Money($amount, new Currency($currency));
    }
}
