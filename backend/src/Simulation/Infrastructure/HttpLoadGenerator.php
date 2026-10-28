<?php

declare(strict_types=1);

namespace App\Simulation\Infrastructure;

use App\Payment\ValueObject\FakePaySignature;
use App\Shared\DependencyInjection\Env;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Simulation\ReadModel\SimulationCounters;
use App\Simulation\ReadModel\SimulationProduct;
use App\Simulation\ReadModel\SimulationRun;
use App\Simulation\Repository\SimulationStoreInterface;
use App\Simulation\Service\LoadGeneratorInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Simulated buyers shop through the public API, as real customers would, through the proxy
 * and the web server: create a cart, add a product, check out, start a FakePay payment, and
 * receive its signed webhook. All buyers share one HTTP client, which sends their requests
 * concurrently; each buyer starts the next order as soon as the last one ends, until the run
 * is over. Requests carry the load test key, so the rate limits do not stop them.
 */
final readonly class HttpLoadGenerator implements LoadGeneratorInterface
{
    private const string CARTS_PATH = '/api/v1/carts';
    private const string CART_ITEM_PATH_FORMAT = '/api/v1/carts/%s/items/%d';
    private const string CHECKOUT_PATH_FORMAT = '/api/v1/carts/%s/checkout';
    private const string PAYMENTS_PATH_FORMAT = '/api/v1/orders/%s/payments';
    private const string WEBHOOK_PATH = '/api/v1/payments/webhooks/fakepay';
    private const string ORDER_NUMBER_FORMAT = 'simulation:%s:%d-%d';
    private const string PAYMENT_KEY_FORMAT = '%s:payment';
    private const string EVENT_ID_FORMAT = 'evt_%s';
    private const string PROVIDER_PAYMENT_PATTERN = '/fp_[a-f0-9]+$/';
    private const string JSON = 'application/json';
    private const string FAKEPAY = 'fakepay';
    private const string SUCCEEDED = 'succeeded';
    private const string CURRENCY = 'PLN';
    private const string ID_FIELD = 'id';
    private const string TOTAL_FIELD = 'total';
    private const string AMOUNT_FIELD = 'amount';
    private const string CHECKOUT_URL_FIELD = 'checkout_url';
    private const string QUANTITY_FIELD = 'quantity';
    private const string PROVIDER_FIELD = 'provider';
    private const string PAYMENT_ID_FIELD = 'payment_id';
    private const string STATUS_FIELD = 'status';
    private const string CURRENCY_FIELD = 'currency';
    private const string USER_DATA_OPTION = 'user_data';
    private const string HEADERS_OPTION = 'headers';
    private const string JSON_OPTION = 'json';
    private const string BODY_OPTION = 'body';
    private const string TIMEOUT_OPTION = 'timeout';
    private const string RESOLVE_OPTION = 'resolve';
    private const float STREAM_TIMEOUT_SECONDS = 1.0;
    private const int MAXIMUM_QUANTITY = 2;
    private const int REQUEST_TIMEOUT_SECONDS = 10;

    private HttpClientInterface $client;

    public function __construct(
        private SimulationStoreInterface $store,
        private ClockInterface $clock,
        #[Env('SIMULATION_BASE_URL')] private string $baseUrl,
        #[Env('SIMULATION_RESOLVE_HOST')] string $resolveHost,
        #[Env('LOAD_TEST_KEY')] private string $loadTestKey,
        #[Env('FAKEPAY_WEBHOOK_SECRET')] private string $webhookSecret,
    ) {
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        $this->client = HttpClient::create([
            self::TIMEOUT_OPTION => self::REQUEST_TIMEOUT_SECONDS,
            // Inside the server the public name points back at the proxy's container.
            self::RESOLVE_OPTION => [$host => gethostbyname($resolveHost)],
            self::HEADERS_OPTION => [HeaderNameEnum::LOAD_TEST_KEY->value => $loadTestKey],
        ]);
    }

    public function run(SimulationRun $run): void
    {
        $productIds = array_map(static fn (SimulationProduct $product): int => $product->productId, $run->productsAtStart);
        $orders = 0;
        $pending = [];

        for ($number = 1; $number <= $run->buyers; $number++) {
            $buyer = new Buyer($number);
            $buyer->startOrder(sprintf(self::ORDER_NUMBER_FORMAT, $run->id, $number, ++$orders));
            $pending[] = $this->createCart($buyer);
        }

        while ($pending !== []) {
            $next = [];

            foreach ($this->client->stream($pending, self::STREAM_TIMEOUT_SECONDS) as $response => $chunk) {
                try {
                    if ($chunk->isTimeout()) {
                        continue;
                    }

                    // Reading the status marks it as handled; otherwise the client throws for
                    // every 4xx answer, such as a checkout refused for lack of stock.
                    $response->getStatusCode();

                    if (!$chunk->isLast()) {
                        continue;
                    }
                } catch (ExceptionInterface) {
                    // The request failed in transport; the buyer's order ends below.
                }

                /** @var Buyer $buyer */
                $buyer = $response->getInfo(self::USER_DATA_OPTION);
                $following = $this->advance($run, $buyer, $response, $productIds);

                if ($following === null && $this->clock->now() < $run->endsAt) {
                    $buyer->startOrder(sprintf(self::ORDER_NUMBER_FORMAT, $run->id, $buyer->number, ++$orders));
                    $following = $this->createCart($buyer);
                }

                if ($following !== null) {
                    $next[] = $following;
                }
            }

            $pending = $next;
        }
    }

    /**
     * Takes the buyer's finished request and sends the next one, or returns null when the
     * order is over: paid, refused for lack of stock, or failed.
     *
     * @param list<int> $productIds
     */
    private function advance(SimulationRun $run, Buyer $buyer, ResponseInterface $response, array $productIds): ?ResponseInterface
    {
        try {
            $status = $response->getStatusCode();
            $body = $status < 300 ? $response->toArray(false) : [];
            $headers = $response->getHeaders(false);
        } catch (ExceptionInterface | TransportException) {
            $this->store->increment($run->id, SimulationCounters::FAILED_REQUESTS);

            return null;
        }

        return match (true) {
            $buyer->step === BuyerStepEnum::CREATE_CART && $status === ResponseStatusEnum::CREATED->value
                => $this->addItem($buyer, (string) $body[self::ID_FIELD], $productIds[array_rand($productIds)]),
            $buyer->step === BuyerStepEnum::ADD_ITEM && $status === ResponseStatusEnum::OK->value
                => $this->checkOut($buyer),
            $buyer->step === BuyerStepEnum::ADD_ITEM && $status === ResponseStatusEnum::CONFLICT->value
                => $this->count($run, SimulationCounters::SOLD_OUT),
            $buyer->step === BuyerStepEnum::CHECK_OUT && $status === ResponseStatusEnum::CREATED->value
                => $this->startPayment($run, $buyer, $body, $headers),
            $buyer->step === BuyerStepEnum::CHECK_OUT && $status === ResponseStatusEnum::CONFLICT->value
                => $this->count($run, SimulationCounters::SOLD_OUT),
            $buyer->step === BuyerStepEnum::START_PAYMENT && $status === ResponseStatusEnum::CREATED->value
                => $this->confirmPayment($buyer, (string) $body[self::CHECKOUT_URL_FIELD]),
            $buyer->step === BuyerStepEnum::CONFIRM_PAYMENT && $status === ResponseStatusEnum::OK->value
                => $this->count($run, SimulationCounters::ORDERS_PAID),
            default => $this->count($run, SimulationCounters::FAILED_REQUESTS),
        };
    }

    private function createCart(Buyer $buyer): ResponseInterface
    {
        $buyer->step = BuyerStepEnum::CREATE_CART;

        return $this->send($buyer, HttpMethodEnum::POST, self::CARTS_PATH);
    }

    private function addItem(Buyer $buyer, string $cartId, int $productId): ResponseInterface
    {
        $buyer->step = BuyerStepEnum::ADD_ITEM;
        $buyer->cartId = $cartId;

        return $this->send($buyer, HttpMethodEnum::PUT, sprintf(self::CART_ITEM_PATH_FORMAT, $cartId, $productId), [self::QUANTITY_FIELD => random_int(1, self::MAXIMUM_QUANTITY)]);
    }

    private function checkOut(Buyer $buyer): ResponseInterface
    {
        $buyer->step = BuyerStepEnum::CHECK_OUT;

        return $this->send($buyer, HttpMethodEnum::POST, sprintf(self::CHECKOUT_PATH_FORMAT, $buyer->cartId), headers: [
            HeaderNameEnum::IDEMPOTENCY_KEY->value => $buyer->orderNumber,
        ]);
    }

    /**
     * @param array<array-key, mixed> $order
     * @param array<string, list<string>> $headers
     */
    private function startPayment(SimulationRun $run, Buyer $buyer, array $order, array $headers): ResponseInterface
    {
        $this->store->increment($run->id, SimulationCounters::ORDERS_PLACED);
        $buyer->step = BuyerStepEnum::START_PAYMENT;
        $buyer->orderId = (string) $order[self::ID_FIELD];
        $buyer->orderToken = $headers[strtolower(HeaderNameEnum::ORDER_TOKEN->value)][0] ?? '';
        /** @var array<string, string> $total */
        $total = $order[self::TOTAL_FIELD];
        $buyer->orderTotal = (int) $total[self::AMOUNT_FIELD];

        return $this->send($buyer, HttpMethodEnum::POST, sprintf(self::PAYMENTS_PATH_FORMAT, $buyer->orderId), [self::PROVIDER_FIELD => self::FAKEPAY], [
            HeaderNameEnum::ORDER_TOKEN->value => $buyer->orderToken,
            HeaderNameEnum::IDEMPOTENCY_KEY->value => sprintf(self::PAYMENT_KEY_FORMAT, $buyer->orderNumber),
        ]);
    }

    private function confirmPayment(Buyer $buyer, string $checkoutUrl): ResponseInterface
    {
        $buyer->step = BuyerStepEnum::CONFIRM_PAYMENT;
        preg_match(self::PROVIDER_PAYMENT_PATTERN, $checkoutUrl, $matches);
        $payload = json_encode([
            self::ID_FIELD => sprintf(self::EVENT_ID_FORMAT, bin2hex(random_bytes(12))),
            self::PAYMENT_ID_FIELD => $matches[0] ?? '',
            self::STATUS_FIELD => self::SUCCEEDED,
            self::AMOUNT_FIELD => $buyer->orderTotal,
            self::CURRENCY_FIELD => self::CURRENCY,
        ], JSON_THROW_ON_ERROR);

        return $this->client->request(HttpMethodEnum::POST->value, $this->baseUrl . self::WEBHOOK_PATH, [
            self::BODY_OPTION => $payload,
            self::HEADERS_OPTION => [HeaderNameEnum::CONTENT_TYPE->value => self::JSON, FakePaySignature::HEADER => FakePaySignature::of($payload, $this->webhookSecret)],
            self::USER_DATA_OPTION => $buyer,
        ]);
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    private function send(Buyer $buyer, HttpMethodEnum $method, string $path, ?array $json = null, array $headers = []): ResponseInterface
    {
        $options = [self::HEADERS_OPTION => $headers, self::USER_DATA_OPTION => $buyer];

        if ($json !== null) {
            $options[self::JSON_OPTION] = $json;
        }

        return $this->client->request($method->value, $this->baseUrl . $path, $options);
    }

    private function count(SimulationRun $run, string $counter): null
    {
        $this->store->increment($run->id, $counter);

        return null;
    }
}
