<?php

declare(strict_types=1);

namespace App\Payment\Request;

use App\Order\Entity\OrderId;
use App\Payment\Command\StartPaymentCommand;
use App\Payment\Enum\PaymentProviderEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\IdempotencyKey;
use App\Shared\Http\Request;

final readonly class StartPaymentRequest
{
    private const string ORDER_ID_PARAMETER = 'id';
    private const string PROVIDER_FIELD = 'provider';
    private const string INVALID_PROVIDER_MESSAGE = 'The "provider" field must name a supported payment provider.';

    public function __construct(
        public OrderId $orderId,
        public PaymentProviderEnum $provider,
        public string $idempotencyKey,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $provider = $request->json()[self::PROVIDER_FIELD] ?? null;

        return new self(
            OrderId::fromString((string) $request->routeParameter(self::ORDER_ID_PARAMETER)),
            (is_string($provider) ? PaymentProviderEnum::tryFrom($provider) : null)
                ?? throw new InvalidRequestException(self::INVALID_PROVIDER_MESSAGE),
            IdempotencyKey::fromRequest($request),
        );
    }

    public function toCommand(): StartPaymentCommand
    {
        return new StartPaymentCommand($this->orderId, $this->provider, $this->idempotencyKey);
    }
}
