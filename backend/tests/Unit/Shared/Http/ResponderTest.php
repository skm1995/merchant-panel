<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Contract\Arrayable;
use App\Shared\Http\EntityTag;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Responder;
use App\Shared\Http\Request;
use App\Shared\Http\Enum\HttpMethodEnum;
use PHPUnit\Framework\TestCase;

final class ResponderTest extends TestCase
{
    private const string VIEW = 'report/order-report';
    private const string HEADING_KEY = 'heading';
    private const string ROWS_KEY = 'rows';
    private const string SUMMARY_KEY = 'summary';
    private const string MESSAGE = '<unsafe message>';
    private const string ESCAPED_MESSAGE = '&lt;unsafe message&gt;';
    private const string BAD_REQUEST_BODY = '400 Bad Request';
    private const string NAME_KEY = 'name';
    private const string NAME = 'Zażółć/gęślą';
    private const string DETAIL = 'The product does not exist.';

    private Responder $responder;

    protected function setUp(): void
    {
        $this->responder = new Responder();
    }

    protected function tearDown(): void
    {
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testRenderEscapesDynamicValues(): void
    {
        ob_start();

        $this->responder->render(self::VIEW, [self::HEADING_KEY => self::MESSAGE, self::ROWS_KEY => [], self::SUMMARY_KEY => self::MESSAGE]);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString(self::ESCAPED_MESSAGE, $output);
        self::assertStringNotContainsString(self::MESSAGE, $output);
    }

    public function testJsonWritesUnescapedJsonWithStatus(): void
    {
        ob_start();

        $this->responder->json(new readonly class (self::NAME_KEY, self::NAME) implements Arrayable {
            public function __construct(private string $key, private string $value)
            {
            }

            public function toArray(): array
            {
                return [$this->key => $this->value];
            }
        });
        $output = ob_get_clean();

        self::assertSame(ResponseStatusEnum::OK->value, http_response_code());
        self::assertSame('{"name":"Zażółć/gęślą"}', $output);
    }

    public function testJsonCanCarryAnEntityTag(): void
    {
        ob_start();
        $this->responder->json(new readonly class () implements Arrayable {
            public function toArray(): array
            {
                return [];
            }
        }, ResponseStatusEnum::OK, EntityTag::fromVersion(3));
        ob_end_clean();

        self::assertSame(ResponseStatusEnum::OK->value, http_response_code());
    }

    public function testProblemFollowsRfc9457(): void
    {
        ob_start();

        $this->responder->problem(ResponseStatusEnum::NOT_FOUND, self::DETAIL);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, http_response_code());
        self::assertSame([
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => self::DETAIL,
        ], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
    }

    public function testStatusWritesPlainStatusLine(): void
    {
        ob_start();

        $this->responder->status(ResponseStatusEnum::BAD_REQUEST);
        $output = ob_get_clean();

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, http_response_code());
        self::assertSame(self::BAD_REQUEST_BODY, $output);
    }

    public function testErrorAnswersApiRequestsWithProblemsAndPagesWithPlainStatus(): void
    {
        ob_start();
        $this->responder->error(new Request(HttpMethodEnum::GET->value, '/api/v1/products'), ResponseStatusEnum::NOT_FOUND, self::DETAIL);
        $problem = (string) ob_get_clean();
        ob_start();
        $this->responder->error(new Request(HttpMethodEnum::GET->value, '/login'), ResponseStatusEnum::NOT_FOUND, self::DETAIL);
        $plain = (string) ob_get_clean();

        self::assertStringContainsString(self::DETAIL, $problem);
        self::assertSame('404 Not Found', $plain);
    }

    public function testWithoutBodyWritesOnlyTheStatus(): void
    {
        ob_start();
        $this->responder->withoutBody(ResponseStatusEnum::NO_CONTENT);
        $output = ob_get_clean();

        self::assertSame(ResponseStatusEnum::NO_CONTENT->value, http_response_code());
        self::assertSame(0, strlen((string) $output));
    }

    public function testPermanentRedirectPointsToTheNewLocation(): void
    {
        $this->responder->permanentRedirect('/products/5/edit');

        self::assertSame(ResponseStatusEnum::MOVED_PERMANENTLY->value, http_response_code());
    }
}
