<?php

declare(strict_types=1);

namespace App\Payment\Request;

use App\Order\Entity\OrderId;
use App\Payment\Command\StartPaymentCommand;
use App\Payment\Enum\PaymentProviderEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\IdempotencyKey;
use App\Shared\Http\Request;

final class StartPaymentRequest
{
    private const string ORDER_ID_PARAMETER = 'id';
    private const string PROVIDER_FIELD = 'provider';
    private const string INVALID_PROVIDER_MESSAGE = 'The "provider" field must name a supported payment provider.';

    public static function toCommand(Request $request): StartPaymentCommand
    {
        $provider = $request->json()[self::PROVIDER_FIELD] ?? null;

        return new StartPaymentCommand(
            OrderId::fromString($request->routeParameter(self::ORDER_ID_PARAMETER)),
            (is_string($provider) ? PaymentProviderEnum::tryFrom($provider) : null)
                ?? throw new InvalidRequestException(self::INVALID_PROVIDER_MESSAGE),
            IdempotencyKey::fromRequest($request),
        );
    }
}
