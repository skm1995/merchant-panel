<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Enum\PaymentOutcomeEnum;
use App\Payment\Exception\InvalidWebhookPayloadException;
use App\Payment\Exception\InvalidWebhookSignatureException;
use App\Payment\Infrastructure\Gateway\FakePayGateway;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Payment\PaymentFixtures;

final class FakePayGatewayTest extends TestCase
{
    private const string PROVIDER_ID_PATTERN = '#^fp_[a-f0-9]{32}$#';
    private const string CHECKOUT_URL_FORMAT = '%s/pay/%s';
    private const string SIGNATURE_HEADER = 'x-fakepay-signature';
    private const string PAYLOAD = '{"id":"evt_1","payment_id":"fp_1","status":"succeeded","amount":2500,"currency":"PLN"}';
    private const string FAILED_PAYLOAD = '{"id":"evt_2","payment_id":"fp_1","status":"failed","amount":2500,"currency":"PLN"}';
    private const string MALFORMED_PAYLOAD = '{"id":"evt_3","payment_id":"fp_1"}';
    private const string HASH_ALGORITHM = 'sha256';

    public function testCreatesRedirectPaymentWithProviderId(): void
    {
        $gateway = new FakePayGateway(PaymentFixtures::CHECKOUT_BASE_URL . '/', PaymentFixtures::WEBHOOK_SECRET);

        $payment = $gateway->createPayment(PaymentFixtures::paymentId(), Money::PLN(2500));

        self::assertMatchesRegularExpression(self::PROVIDER_ID_PATTERN, $payment->providerPaymentId);
        self::assertSame(
            sprintf(self::CHECKOUT_URL_FORMAT, PaymentFixtures::CHECKOUT_BASE_URL, $payment->providerPaymentId),
            $payment->checkoutUrl,
        );
    }

    public function testParsesSignedWebhook(): void
    {
        $event = self::gateway()->parseWebhook(self::PAYLOAD, self::signed(self::PAYLOAD));

        self::assertSame('evt_1', $event->eventId);
        self::assertSame('fp_1', $event->providerPaymentId);
        self::assertSame(PaymentOutcomeEnum::SUCCEEDED, $event->outcome);
        self::assertTrue(Money::PLN(2500)->equals($event->amount));
        self::assertSame(
            PaymentOutcomeEnum::FAILED,
            self::gateway()->parseWebhook(self::FAILED_PAYLOAD, self::signed(self::FAILED_PAYLOAD))->outcome,
        );
    }

    public function testRejectsWebhookWithWrongOrMissingSignature(): void
    {
        foreach ([[self::SIGNATURE_HEADER => str_repeat('0', 64)], []] as $headers) {
            try {
                self::gateway()->parseWebhook(self::PAYLOAD, $headers);
                self::fail('Expected the signature to be rejected.');
            } catch (InvalidWebhookSignatureException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRejectsSignedButMalformedPayload(): void
    {
        $this->expectException(InvalidWebhookPayloadException::class);

        self::gateway()->parseWebhook(self::MALFORMED_PAYLOAD, self::signed(self::MALFORMED_PAYLOAD));
    }

    private static function gateway(): FakePayGateway
    {
        return new FakePayGateway(PaymentFixtures::CHECKOUT_BASE_URL, PaymentFixtures::WEBHOOK_SECRET);
    }

    /** @return array<string, string> */
    private static function signed(string $payload): array
    {
        return [self::SIGNATURE_HEADER => hash_hmac(self::HASH_ALGORITHM, $payload, PaymentFixtures::WEBHOOK_SECRET)];
    }
}
