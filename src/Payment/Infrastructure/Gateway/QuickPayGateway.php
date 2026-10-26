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
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exception\ParserException;
use Money\Parser\DecimalMoneyParser;
use Money\Money;
use Psr\Clock\ClockInterface;

/**
 * Simulated provider with a different webhook format whose signature covers a timestamp,
 * so old webhooks cannot be replayed.
 */
final class QuickPayGateway implements PaymentGatewayInterface
{
    public const int SIGNATURE_TOLERANCE_SECONDS = 300;
    private const string PROVIDER_ID_PREFIX = 'qp_';
    private const string CHECKOUT_URL_FORMAT = '%s/checkout?reference=%s';
    private const string URL_PATH_SEPARATOR = '/';
    private const string SIGNATURE_HEADER = 'x-quickpay-signature';
    private const string SIGNATURE_PATTERN = '/^t=(\d+),v1=([a-f0-9]{64})$/';
    private const string SIGNED_CONTENT_FORMAT = '%d.%s';
    private const string HASH_ALGORITHM = 'sha256';
    private const string EVENT_ID_FIELD = 'event.id';
    private const string REFERENCE_FIELD = 'transaction.reference';
    private const string STATE_FIELD = 'transaction.state';
    private const string AMOUNT_FIELD = 'transaction.total.value';
    private const string CURRENCY_FIELD = 'transaction.total.currency';
    private const string PAID_STATE = 'PAID';
    private const string REJECTED_STATE = 'REJECTED';

    public function __construct(
        private readonly string $checkoutBaseUrl,
        private readonly string $webhookSecret,
        private readonly ClockInterface $clock,
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
        $this->verifySignature($payload, $headers[self::SIGNATURE_HEADER] ?? null);
        $data = WebhookPayload::decode($payload);

        return new PaymentWebhookEvent(
            $data->string(self::EVENT_ID_FIELD),
            $data->string(self::REFERENCE_FIELD),
            match ($data->string(self::STATE_FIELD)) {
                self::PAID_STATE => PaymentOutcomeEnum::SUCCEEDED,
                self::REJECTED_STATE => PaymentOutcomeEnum::FAILED,
                default => throw new InvalidWebhookPayloadException(),
            },
            self::parseAmount($data->string(self::AMOUNT_FIELD), $data->string(self::CURRENCY_FIELD)),
        );
    }

    /**
     * The signature covers the timestamp, and timestamps outside the tolerance are rejected,
     * so a captured webhook cannot be replayed later.
     */
    private function verifySignature(string $payload, ?string $header): void
    {
        if ($header === null || preg_match(self::SIGNATURE_PATTERN, $header, $matches) !== 1) {
            throw new InvalidWebhookSignatureException();
        }

        $timestamp = (int) $matches[1];
        $expected = hash_hmac(
            self::HASH_ALGORITHM,
            sprintf(self::SIGNED_CONTENT_FORMAT, $timestamp, $payload),
            $this->webhookSecret,
        );
        $age = abs($this->clock->now()->getTimestamp() - $timestamp);

        if ($age > self::SIGNATURE_TOLERANCE_SECONDS || !hash_equals($expected, $matches[2])) {
            throw new InvalidWebhookSignatureException();
        }
    }

    /** @param non-empty-string $currencyCode */
    private static function parseAmount(string $decimal, string $currencyCode): Money
    {
        try {
            return new DecimalMoneyParser(new ISOCurrencies())->parse($decimal, new Currency($currencyCode));
        } catch (ParserException) {
            throw new InvalidWebhookPayloadException();
        }
    }
}
