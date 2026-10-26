<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ServerParameterEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private const string PATH = '/confirm';
    private const string URI = '/confirm?token=abc';
    private const string FIELD = 'token';
    private const string VALUE = 'abc';
    private const string IDEMPOTENCY_HEADER = 'Idempotency-Key';
    private const string IDEMPOTENCY_SERVER_KEY = 'HTTP_IDEMPOTENCY_KEY';
    private const string KEY = 'checkout-1';
    private const string JSON_BODY = '{"quantity":2}';
    private const string INVALID_JSON_BODY = '{"quantity":';
    private const string JSON_LIST_BODY = '[1,2]';

    /** @var array<mixed> */
    private array $originalServer;
    /** @var array<mixed> */
    private array $originalGet;
    /** @var array<mixed> */
    private array $originalPost;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
    }

    public function testReadsMethodPathAndInputFromGlobals(): void
    {
        $_SERVER[ServerParameterEnum::REQUEST_METHOD->value] = HttpMethodEnum::POST->value;
        $_SERVER[ServerParameterEnum::REQUEST_URI->value] = self::URI;
        $_GET = [self::FIELD => self::VALUE];
        $_POST = [self::FIELD => self::VALUE];

        $request = Request::fromGlobals();

        self::assertSame(HttpMethodEnum::POST->value, $request->method);
        self::assertSame(self::PATH, $request->path);
        self::assertSame(self::VALUE, $request->query(self::FIELD));
        self::assertSame(self::VALUE, $request->post(self::FIELD));
    }

    public function testFallsBackToRootGetRequestForInvalidServerParameters(): void
    {
        $_SERVER = [
            ServerParameterEnum::REQUEST_METHOD->value => [],
            ServerParameterEnum::REQUEST_URI->value => [],
        ];

        $request = Request::fromGlobals();

        self::assertSame(HttpMethodEnum::GET->value, $request->method);
        self::assertSame(Request::ROOT_PATH, $request->path);
    }

    public function testCarriesRouteParametersWithoutMutatingOriginal(): void
    {
        $request = new Request(HttpMethodEnum::GET->value, self::PATH);

        $routed = $request->withRouteParameters([self::FIELD => self::VALUE]);

        self::assertSame(self::VALUE, $routed->routeParameter(self::FIELD));

        $this->expectException(LogicException::class);
        $request->routeParameter(self::FIELD);
    }

    public function testRecognizesApiPaths(): void
    {
        self::assertTrue(new Request(HttpMethodEnum::GET->value, '/api/v1/products')->isApi());
        self::assertFalse(new Request(HttpMethodEnum::GET->value, '/apiary')->isApi());
        self::assertFalse(new Request(HttpMethodEnum::GET->value, self::PATH)->isApi());
    }

    public function testReadsHeadersCaseInsensitivelyFromGlobals(): void
    {
        $_SERVER[self::IDEMPOTENCY_SERVER_KEY] = self::KEY;

        $request = Request::fromGlobals();

        self::assertSame(self::KEY, $request->header(self::IDEMPOTENCY_HEADER));
        self::assertSame(self::KEY, $request->header(strtolower(self::IDEMPOTENCY_HEADER)));
    }

    public function testDecodesJsonObjectBody(): void
    {
        $request = new Request(HttpMethodEnum::PUT->value, self::PATH, body: self::JSON_BODY);

        self::assertSame(['quantity' => 2], $request->json());
    }

    public function testExposesRawBodyAndHeaders(): void
    {
        $request = new Request(HttpMethodEnum::POST->value, self::PATH, headers: ['x-signature' => self::KEY], body: self::JSON_BODY);

        self::assertSame(self::JSON_BODY, $request->body());
        self::assertSame(['x-signature' => self::KEY], $request->headers());
    }

    public function testRejectsMalformedJsonBody(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Request(HttpMethodEnum::PUT->value, self::PATH, body: self::INVALID_JSON_BODY)->json();
    }

    public function testRejectsJsonBodyThatIsNotAnObject(): void
    {
        $this->expectException(InvalidRequestException::class);

        new Request(HttpMethodEnum::PUT->value, self::PATH, body: self::JSON_LIST_BODY)->json();
    }

    public function testReturnsNullForMissingOrNonStringInput(): void
    {
        $request = new Request(HttpMethodEnum::POST->value, self::PATH, [], [self::FIELD => [self::VALUE]]);

        self::assertNull($request->query(self::FIELD));
        self::assertNull($request->post(self::FIELD));
    }
}
