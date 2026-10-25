<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ServerParameterEnum;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private const string PATH = '/confirm';
    private const string URI = '/confirm?token=abc';
    private const string FIELD = 'token';
    private const string VALUE = 'abc';

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

    public function testReturnsNullForMissingOrNonStringInput(): void
    {
        $request = new Request(HttpMethodEnum::POST->value, self::PATH, [], [self::FIELD => [self::VALUE]]);

        self::assertNull($request->query(self::FIELD));
        self::assertNull($request->post(self::FIELD));
    }
}
