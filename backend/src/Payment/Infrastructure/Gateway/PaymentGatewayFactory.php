<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Gateway\PaymentGatewayFactoryInterface;
use App\Payment\Gateway\PaymentGatewayInterface;
use App\Shared\Config\Environment;
use Psr\Clock\ClockInterface;

/**
 * Builds the gateway for a provider from its environment configuration on demand, so a
 * missing configuration only affects the provider that needs it.
 */
final class PaymentGatewayFactory implements PaymentGatewayFactoryInterface
{
    private const string FAKEPAY_CHECKOUT_URL = 'FAKEPAY_CHECKOUT_URL';
    private const string FAKEPAY_WEBHOOK_SECRET = 'FAKEPAY_WEBHOOK_SECRET';
    private const string QUICKPAY_CHECKOUT_URL = 'QUICKPAY_CHECKOUT_URL';
    private const string QUICKPAY_WEBHOOK_SECRET = 'QUICKPAY_WEBHOOK_SECRET';

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function create(PaymentProviderEnum $provider): PaymentGatewayInterface
    {
        return match ($provider) {
            PaymentProviderEnum::FAKEPAY => new FakePayGateway(
                Environment::get(self::FAKEPAY_CHECKOUT_URL),
                Environment::get(self::FAKEPAY_WEBHOOK_SECRET),
            ),
            PaymentProviderEnum::QUICKPAY => new QuickPayGateway(
                Environment::get(self::QUICKPAY_CHECKOUT_URL),
                Environment::get(self::QUICKPAY_WEBHOOK_SECRET),
                $this->clock,
            ),
        };
    }
}
