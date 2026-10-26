<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Enum\PaymentOutcomeEnum;
use App\Payment\Exception\InvalidWebhookSignatureException;
use App\Payment\Infrastructure\Gateway\QuickPayGateway;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Payment\PaymentFixtures;
use Tests\Unit\Support\FrozenClock;

final class QuickPayGatewayTest extends TestCase
{
    private const string NOW = '2026-10-25 12:00:00';
    private const string SIGNATURE_HEADER = 'x-quickpay-signature';
    private const string SIGNATURE_FORMAT = 't=%d,v1=%s';
    private const string SIGNED_CONTENT_FORMAT = '%d.%s';
    private const string HASH_ALGORITHM = 'sha256';
    private const string PAYLOAD = '{"event":{"id":"evt_9"},"transaction":{"reference":"qp_9","state":"PAID","total":{"value":"25.00","currency":"PLN"}}}';
    private const string CHECKOUT_URL_PATTERN = '#^https://pay\.example\.test/checkout\?reference=qp_[a-f0-9]{32}$#';

    public function testCreatesPaymentWithReferenceInCheckoutUrl(): void
    {
        $gateway = new QuickPayGateway(PaymentFixtures::CHECKOUT_BASE_URL, PaymentFixtures::WEBHOOK_SECRET, new FrozenClock());

        $payment = $gateway->createPayment(PaymentFixtures::paymentId(), Money::PLN(2500));

        self::assertMatchesRegularExpression(self::CHECKOUT_URL_PATTERN, $payment->checkoutUrl);
        self::assertTrue(str_ends_with($payment->checkoutUrl, $payment->providerPaymentId));
    }

    public function testParsesTimestampSignedWebhookWithDecimalAmount(): void
    {
        $clock = new FrozenClock(self::NOW);

        $event = self::gateway($clock)->parseWebhook(self::PAYLOAD, self::signed(self::PAYLOAD, $clock->now()->getTimestamp()));

        self::assertSame('evt_9', $event->eventId);
        self::assertSame('qp_9', $event->providerPaymentId);
        self::assertSame(PaymentOutcomeEnum::SUCCEEDED, $event->outcome);
        self::assertTrue(Money::PLN(2500)->equals($event->amount));
    }

    public function testRejectsReplayedWebhookOutsideTheTolerance(): void
    {
        $clock = new FrozenClock(self::NOW);
        $staleTimestamp = $clock->now()->getTimestamp() - QuickPayGateway::SIGNATURE_TOLERANCE_SECONDS - 1;

        $this->expectException(InvalidWebhookSignatureException::class);
        self::gateway($clock)->parseWebhook(self::PAYLOAD, self::signed(self::PAYLOAD, $staleTimestamp));
    }

    public function testRejectsSignatureOverDifferentContent(): void
    {
        $clock = new FrozenClock(self::NOW);
        $headers = self::signed(self::PAYLOAD, $clock->now()->getTimestamp());

        $this->expectException(InvalidWebhookSignatureException::class);
        self::gateway($clock)->parseWebhook(str_replace('25.00', '0.01', self::PAYLOAD), $headers);
    }

    private static function gateway(FrozenClock $clock): QuickPayGateway
    {
        return new QuickPayGateway(PaymentFixtures::CHECKOUT_BASE_URL, PaymentFixtures::WEBHOOK_SECRET, $clock);
    }

    /** @return array<string, string> */
    private static function signed(string $payload, int $timestamp): array
    {
        $signature = hash_hmac(
            self::HASH_ALGORITHM,
            sprintf(self::SIGNED_CONTENT_FORMAT, $timestamp, $payload),
            PaymentFixtures::WEBHOOK_SECRET,
        );

        return [self::SIGNATURE_HEADER => sprintf(self::SIGNATURE_FORMAT, $timestamp, $signature)];
    }
}
