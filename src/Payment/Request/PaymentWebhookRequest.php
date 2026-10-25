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
final readonly class PaymentWebhookRequest
{
    private const string PROVIDER_PARAMETER = 'provider';
    private const string MISSING_BODY_MESSAGE = 'The webhook body is missing.';

    /** @param array<string, string> $headers */
    public function __construct(public PaymentProviderEnum $provider, public string $payload, public array $headers)
    {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            PaymentProviderEnum::tryFrom((string) $request->routeParameter(self::PROVIDER_PARAMETER))
                ?? throw new UnknownPaymentProviderException(),
            $request->body() ?? throw new InvalidRequestException(self::MISSING_BODY_MESSAGE),
            $request->headers(),
        );
    }

    public function toCommand(): HandlePaymentWebhookCommand
    {
        return new HandlePaymentWebhookCommand($this->provider, $this->payload, $this->headers);
    }
}
