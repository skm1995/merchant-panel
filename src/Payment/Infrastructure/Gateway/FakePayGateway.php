<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Entity\PaymentId;
use App\Payment\Enum\PaymentOutcomeEnum;
use App\Payment\Exception\InvalidWebhookPayloadException;
use App\Payment\Exception\InvalidWebhookSignatureException;
use App\Payment\Gateway\GatewayPayment;
use App\Payment\Gateway\PaymentGatewayInterface;
use App\Payment\Gateway\PaymentWebhookEvent;
use Money\Currency;
use Money\Money;

/**
 * Simulated redirect provider: the customer pays on a hosted page and the provider
 * reports the result with webhooks signed by HMAC-SHA256 of the raw body.
 */
final class FakePayGateway implements PaymentGatewayInterface
{
    private const string PROVIDER_ID_PREFIX = 'fp_';
    private const string CHECKOUT_URL_FORMAT = '%s/pay/%s';
    private const string URL_PATH_SEPARATOR = '/';
    private const string SIGNATURE_HEADER = 'x-fakepay-signature';
    private const string HASH_ALGORITHM = 'sha256';
    private const string EVENT_ID_FIELD = 'id';
    private const string PAYMENT_ID_FIELD = 'payment_id';
    private const string STATUS_FIELD = 'status';
    private const string AMOUNT_FIELD = 'amount';
    private const string CURRENCY_FIELD = 'currency';

    public function __construct(
        private readonly string $checkoutBaseUrl,
        private readonly string $webhookSecret,
    ) {
    }

    public function createPayment(PaymentId $paymentId, Money $amount): GatewayPayment
    {
        $providerPaymentId = ProviderPaymentIdGenerator::generate(self::PROVIDER_ID_PREFIX);

        return new GatewayPayment(
            $providerPaymentId,
            sprintf(self::CHECKOUT_URL_FORMAT, rtrim($this->checkoutBaseUrl, self::URL_PATH_SEPARATOR), $providerPaymentId),
        );
    }

    public function parseWebhook(string $payload, array $headers): PaymentWebhookEvent
    {
        $signature = $headers[self::SIGNATURE_HEADER] ?? throw new InvalidWebhookSignatureException();

        if (!hash_equals(hash_hmac(self::HASH_ALGORITHM, $payload, $this->webhookSecret), $signature)) {
            throw new InvalidWebhookSignatureException();
        }

        $data = WebhookPayload::decode($payload);
        $currency = $data->string(self::CURRENCY_FIELD);

        return new PaymentWebhookEvent(
            $data->string(self::EVENT_ID_FIELD),
            $data->string(self::PAYMENT_ID_FIELD),
            PaymentOutcomeEnum::tryFrom($data->string(self::STATUS_FIELD)) ?? throw new InvalidWebhookPayloadException(),
            new Money($data->integer(self::AMOUNT_FIELD), new Currency($currency)),
        );
    }
}
