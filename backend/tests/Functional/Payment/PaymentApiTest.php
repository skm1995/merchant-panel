<?php

declare(strict_types=1);

namespace Tests\Functional\Payment;

use App\Cart\Entity\CartId;
use App\Catalog\Repository\PdoProductRepository;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\TestCartData;
use Tests\Functional\Support\TestCheckout;
use Tests\Functional\Support\TestProductData;
use Tests\Functional\Support\WebhookSigner;

final class PaymentApiTest extends TestCase
{
    private const string SKU_PREFIX = 'payment-';
    private const string KEY_PREFIX = 'payment-key-';
    private const string EVENT_PREFIX = 'evt-';
    private const string ORDER_PATH_FORMAT = '/api/v1/orders/%s';
    private const string CANCEL_PATH_FORMAT = '/api/v1/orders/%s/cancel';
    private const string PAYMENTS_PATH_FORMAT = '/api/v1/orders/%s/payments';
    private const string PAYMENT_PATH_FORMAT = '/api/v1/payments/%s';
    private const string WEBHOOK_PATH_FORMAT = '/api/v1/payments/webhooks/%s';
    private const string START_SPECIFICATION_PATH = '/api/v1/orders/{id}/payments';
    private const string WEBHOOK_SPECIFICATION_PATH = '/api/v1/payments/webhooks/{provider}';
    private const string PAYMENT_SPECIFICATION_PATH = '/api/v1/payments/{id}';
    private const string WRONG_TOKEN = '0000000000000000000000000000000000000000000000000000000000000000';
    private const string FAKEPAY = 'fakepay';
    private const string QUICKPAY = 'quickpay';
    private const string FIRST_VERSION_TAG = '"1"';
    private const int PRICE_IN_GROSZE = 1250;
    private const int STOCK = 5;
    private const int QUANTITY = 2;
    private const int CONCURRENT_DELIVERIES = 5;

    private PDO $pdo;
    private PdoProductRepository $products;
    private string $prefix;
    private int $productId;
    /** @var list<CartId> */
    private array $cartIds = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::createFromEnvironment();
        $this->products = new PdoProductRepository($this->pdo);
        $this->prefix = TestProductData::uniquePrefix(self::SKU_PREFIX);
        [$this->productId] = TestProductData::create($this->pdo, $this->prefix, 1, self::PRICE_IN_GROSZE, self::STOCK);
    }

    protected function tearDown(): void
    {
        TestCartData::delete($this->pdo, $this->cartIds);
        TestProductData::delete($this->pdo, $this->prefix);
    }

    public function testStartsPaymentOnceForRepeatedRequests(): void
    {
        $order = $this->placeOrder();
        $key = uniqid(self::KEY_PREFIX, true);

        $first = $this->startPayment($order, self::FAKEPAY, $key);
        $repeated = $this->startPayment($order, self::FAKEPAY, $key);
        $payment = ApiClient::json($first);

        self::assertSame(ResponseStatusEnum::CREATED->value, $first->getStatusCode());
        ApiClient::assertMatchesSpecification(self::START_SPECIFICATION_PATH, HttpMethodEnum::POST->value, $first);
        self::assertSame(sprintf(self::PAYMENT_PATH_FORMAT, $payment['id']), $first->getHeaderLine(HeaderNameEnum::LOCATION->value));
        self::assertSame('pending', $payment['status']);
        self::assertSame(['amount' => (string) (self::QUANTITY * self::PRICE_IN_GROSZE), 'currency' => 'PLN'], $payment['amount']);
        self::assertSame($payment['id'], ApiClient::json($repeated)['id']);
    }

    public function testOnlyTheOrderAccessTokenOpensPaymentsOfTheOrder(): void
    {
        $order = $this->placeOrder();
        $payment = ApiClient::json($this->startPayment($order, self::FAKEPAY, uniqid(self::KEY_PREFIX, true)));
        $paymentPath = sprintf(self::PAYMENT_PATH_FORMAT, $payment['id']);

        $withoutToken = ApiClient::send(HttpMethodEnum::POST, sprintf(self::PAYMENTS_PATH_FORMAT, $order->orderId), ['provider' => self::FAKEPAY], [
            HeaderNameEnum::IDEMPOTENCY_KEY->value => uniqid(self::KEY_PREFIX, true),
        ]);
        $wrongToken = ApiClient::sendWithQuery($paymentPath, [], [HeaderNameEnum::ORDER_TOKEN->value => self::WRONG_TOKEN]);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $withoutToken->getStatusCode());
        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $wrongToken->getStatusCode());
        ApiClient::assertMatchesSpecification(self::START_SPECIFICATION_PATH, HttpMethodEnum::POST->value, $withoutToken);
        ApiClient::assertMatchesSpecification(self::PAYMENT_SPECIFICATION_PATH, HttpMethodEnum::GET->value, $wrongToken);
    }

    public function testRejectsUnknownProviderAndOrdersThatCannotBePaid(): void
    {
        $order = $this->placeOrder();
        $this->cancel($order);

        $unknownProvider = $this->startPayment($order, 'cash', uniqid(self::KEY_PREFIX, true));
        $cancelledOrder = $this->startPayment($order, self::FAKEPAY, uniqid(self::KEY_PREFIX, true));

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $unknownProvider->getStatusCode());
        self::assertSame(ResponseStatusEnum::CONFLICT->value, $cancelledOrder->getStatusCode());
    }

    public function testSignedWebhookPaysTheOrderAndSellsTheReservedStockOnce(): void
    {
        $order = $this->placeOrder();
        $payment = ApiClient::json($this->startPayment($order, self::FAKEPAY, uniqid(self::KEY_PREFIX, true)));
        $payload = $this->fakepayPayload($payment, 'succeeded');

        $processed = $this->deliverFakepay($payload);
        $duplicate = $this->deliverFakepay($payload);

        ApiClient::assertMatchesSpecification(self::WEBHOOK_SPECIFICATION_PATH, HttpMethodEnum::POST->value, $processed);
        self::assertSame(['status' => 'processed'], ApiClient::json($processed));
        self::assertSame(['status' => 'duplicate'], ApiClient::json($duplicate));
        self::assertSame('paid', $this->show(self::ORDER_PATH_FORMAT, $order->orderId, $order)['status']);
        self::assertSame('succeeded', $this->show(self::PAYMENT_PATH_FORMAT, (string) $payment['id'], $order)['status']);
        $product = $this->products->findById($this->productId);
        self::assertNotNull($product);
        self::assertSame(self::STOCK - self::QUANTITY, $product->stock);
        self::assertSame(0, $product->reserved);
    }

    public function testConcurrentDeliveriesOfOneWebhookAreAppliedOnce(): void
    {
        $order = $this->placeOrder();
        $payment = ApiClient::json($this->startPayment($order, self::FAKEPAY, uniqid(self::KEY_PREFIX, true)));
        $payload = $this->fakepayPayload($payment, 'succeeded');
        $delivery = [HttpMethodEnum::POST, sprintf(self::WEBHOOK_PATH_FORMAT, self::FAKEPAY), WebhookSigner::fakepay($payload), $payload];

        $results = array_count_values(array_map(
            static fn (ResponseInterface $response): string => (string) ApiClient::json($response)['status'],
            ApiClient::sendConcurrently(array_fill(0, self::CONCURRENT_DELIVERIES, $delivery)),
        ));

        ksort($results);

        self::assertSame(['duplicate' => self::CONCURRENT_DELIVERIES - 1, 'processed' => 1], $results);
        self::assertSame(self::STOCK - self::QUANTITY, $this->products->findById($this->productId)?->stock);
    }

    public function testRejectsForgedOrMismatchedWebhooksWithoutChanges(): void
    {
        $order = $this->placeOrder();
        $payment = ApiClient::json($this->startPayment($order, self::FAKEPAY, uniqid(self::KEY_PREFIX, true)));
        $payload = $this->fakepayPayload($payment, 'succeeded');
        $tampered = str_replace('"amount":' . $this->show(self::PAYMENT_PATH_FORMAT, (string) $payment['id'], $order)['amount']['amount'], '"amount":1', $payload);

        $forged = ApiClient::sendRaw(HttpMethodEnum::POST, sprintf(self::WEBHOOK_PATH_FORMAT, self::FAKEPAY), $tampered, WebhookSigner::fakepay($payload));
        $mismatched = $this->deliverFakepay($tampered);

        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, $forged->getStatusCode());
        self::assertSame(ResponseStatusEnum::UNPROCESSABLE_CONTENT->value, $mismatched->getStatusCode());
        self::assertSame('pending_payment', $this->show(self::ORDER_PATH_FORMAT, $order->orderId, $order)['status']);
    }

    public function testPaymentForACancelledOrderRequiresARefund(): void
    {
        $order = $this->placeOrder();
        $payment = ApiClient::json($this->startPayment($order, self::QUICKPAY, uniqid(self::KEY_PREFIX, true)));
        $this->cancel($order);
        $payload = json_encode([
            'event' => ['id' => uniqid(self::EVENT_PREFIX, true)],
            'transaction' => [
                'reference' => self::providerPaymentId($payment),
                'state' => 'PAID',
                'total' => ['value' => '25.00', 'currency' => 'PLN'],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = ApiClient::sendRaw(HttpMethodEnum::POST, sprintf(self::WEBHOOK_PATH_FORMAT, self::QUICKPAY), $payload, WebhookSigner::quickpay($payload));

        self::assertSame(['status' => 'processed'], ApiClient::json($response));
        self::assertSame('refund_required', $this->show(self::PAYMENT_PATH_FORMAT, (string) $payment['id'], $order)['status']);
        self::assertSame(self::STOCK, $this->products->findById($this->productId)?->availableQuantity());
    }

    private function placeOrder(): TestCheckout
    {
        $order = TestCheckout::place($this->productId, self::QUANTITY);
        $this->cartIds[] = $order->cartId;

        return $order;
    }

    private function startPayment(TestCheckout $order, string $provider, string $key): ResponseInterface
    {
        return ApiClient::send(
            HttpMethodEnum::POST,
            sprintf(self::PAYMENTS_PATH_FORMAT, $order->orderId),
            ['provider' => $provider],
            $order->headers([HeaderNameEnum::IDEMPOTENCY_KEY->value => $key]),
        );
    }

    /** @return array<string, mixed> */
    private function show(string $pathFormat, string $id, TestCheckout $order): array
    {
        return ApiClient::json(ApiClient::sendWithQuery(sprintf($pathFormat, $id), [], $order->headers()));
    }

    private function cancel(TestCheckout $order): void
    {
        ApiClient::send(HttpMethodEnum::POST, sprintf(self::CANCEL_PATH_FORMAT, $order->orderId), headers: $order->headers([
            HeaderNameEnum::IF_MATCH->value => self::FIRST_VERSION_TAG,
        ]));
    }

    /** @param array<string, mixed> $payment */
    private function fakepayPayload(array $payment, string $status): string
    {
        return json_encode([
            'id' => uniqid(self::EVENT_PREFIX, true),
            'payment_id' => self::providerPaymentId($payment),
            'status' => $status,
            'amount' => self::QUANTITY * self::PRICE_IN_GROSZE,
            'currency' => 'PLN',
        ], JSON_THROW_ON_ERROR);
    }

    private function deliverFakepay(string $payload): ResponseInterface
    {
        return ApiClient::sendRaw(HttpMethodEnum::POST, sprintf(self::WEBHOOK_PATH_FORMAT, self::FAKEPAY), $payload, WebhookSigner::fakepay($payload));
    }

    /** @param array<string, mixed> $payment */
    private static function providerPaymentId(array $payment): string
    {
        $checkoutUrl = $payment['checkout_url'];
        self::assertIsString($checkoutUrl);
        return preg_match('/(fp|qp)_[a-f0-9]{32}$/', $checkoutUrl, $matches) === 1
            ? $matches[0]
            : self::fail('The checkout URL does not end with a provider payment id.');
    }
}
