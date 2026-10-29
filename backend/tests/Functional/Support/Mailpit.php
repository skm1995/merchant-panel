<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Shared\Config\Environment;
use App\Shared\Http\Enum\HttpMethodEnum;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the emails the worker sent to the test stack's Mailpit through its HTTP API.
 */
final readonly class Mailpit
{
    private const string BASE_URL_ENVIRONMENT_KEY = 'MAILPIT_API_BASE_URL';
    private const string SEARCH_PATH = '/api/v1/search';
    private const string SEARCH_QUERY_FORMAT = 'to:"%s"';
    private const string MESSAGE_PATH_FORMAT = '/api/v1/message/%s';
    private const string MESSAGES_PATH = '/api/v1/messages';
    private const string URL_PATH_SEPARATOR = '/';
    private const string DELETE_METHOD = 'DELETE';
    private const string TIMEOUT_MESSAGE_FORMAT = 'Timed out waiting for an email to %s in Mailpit.';
    private const int WAIT_TIMEOUT_SECONDS = 15;
    private const int POLL_INTERVAL_MICROSECONDS = 100000;

    private HttpClientInterface $client;

    public function __construct()
    {
        $this->client = HttpClient::createForBaseUri(rtrim(
            Environment::get(self::BASE_URL_ENVIRONMENT_KEY),
            self::URL_PATH_SEPARATOR,
        ));
    }

    /**
     * Waits until an email to the address arrives and returns its id.
     */
    public function waitForMessageTo(string $email): string
    {
        $deadline = microtime(true) + self::WAIT_TIMEOUT_SECONDS;

        do {
            $data = $this->client->request(HttpMethodEnum::GET->value, self::SEARCH_PATH, [
                'query' => ['query' => sprintf(self::SEARCH_QUERY_FORMAT, $email)],
            ])->toArray();
            $message = $data['messages'][0] ?? null;
            $messageId = is_array($message) ? ($message['ID'] ?? null) : null;

            if (is_string($messageId)) {
                return $messageId;
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        Assert::fail(sprintf(self::TIMEOUT_MESSAGE_FORMAT, $email));
    }

    public function text(string $messageId): string
    {
        $text = $this->client->request(HttpMethodEnum::GET->value, sprintf(self::MESSAGE_PATH_FORMAT, rawurlencode($messageId)))
            ->toArray()['Text'] ?? null;
        Assert::assertIsString($text);

        return $text;
    }

    public function delete(string $messageId): void
    {
        $this->client->request(self::DELETE_METHOD, self::MESSAGES_PATH, [
            'json' => ['IDs' => [$messageId]],
        ])->getStatusCode();
    }
}
