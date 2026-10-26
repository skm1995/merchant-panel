<?php

declare(strict_types=1);

namespace App\Payment\Request;

use App\Payment\Command\HandlePaymentWebhookCommand;
use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Exception\UnknownPaymentProviderException;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;

/**
 * Keeps the raw body and headers, because the provider signature covers the exact bytes sent.
 */
final class PaymentWebhookRequest
{
    private const string PROVIDER_PARAMETER = 'provider';
    private const string MISSING_BODY_MESSAGE = 'The webhook body is missing.';

    public static function toCommand(Request $request): HandlePaymentWebhookCommand
    {
        return new HandlePaymentWebhookCommand(
            PaymentProviderEnum::tryFrom($request->routeParameter(self::PROVIDER_PARAMETER))
                ?? throw new UnknownPaymentProviderException(),
            $request->body() ?? throw new InvalidRequestException(self::MISSING_BODY_MESSAGE),
            $request->headers(),
        );
    }
}
