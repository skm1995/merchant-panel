<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Contract\Arrayable;
use App\Shared\Http\EntityTag;
use App\Shared\Http\Enum\ContentTypeEnum;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\RecordingResponseWriter;

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
    private const string NEW_LOCATION = '/products/5/edit';

    private RecordingResponseWriter $response;
    private Responder $responder;

    protected function setUp(): void
    {
        $this->response = new RecordingResponseWriter();
        $this->responder = new Responder($this->response);
    }

    public function testRenderEscapesDynamicValues(): void
    {
        $this->responder->render(self::VIEW, [self::HEADING_KEY => self::MESSAGE, self::ROWS_KEY => [], self::SUMMARY_KEY => self::MESSAGE]);

        self::assertSame(ContentTypeEnum::HTML->value, $this->response->headers[HeaderNameEnum::CONTENT_TYPE->value]);
        self::assertStringContainsString(self::ESCAPED_MESSAGE, $this->response->body);
        self::assertStringNotContainsString(self::MESSAGE, $this->response->body);
    }

    public function testJsonWritesUnescapedJsonWithStatus(): void
    {
        $this->responder->json(new readonly class (self::NAME_KEY, self::NAME) implements Arrayable {
            public function __construct(private string $key, private string $value)
            {
            }

            public function toArray(): array
            {
                return [$this->key => $this->value];
            }
        });

        self::assertSame(ResponseStatusEnum::OK->value, $this->response->status);
        self::assertSame(ContentTypeEnum::JSON->value, $this->response->headers[HeaderNameEnum::CONTENT_TYPE->value]);
        self::assertSame('{"name":"Zażółć/gęślą"}', $this->response->body);
    }

    public function testJsonCanCarryAnEntityTag(): void
    {
        $this->responder->json(new readonly class () implements Arrayable {
            public function toArray(): array
            {
                return [];
            }
        }, ResponseStatusEnum::OK, EntityTag::fromVersion(3));

        self::assertSame(EntityTag::fromVersion(3)->value, $this->response->headers[HeaderNameEnum::ETAG->value]);
    }

    public function testProblemFollowsRfc9457(): void
    {
        $this->responder->problem(ResponseStatusEnum::NOT_FOUND, self::DETAIL);

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, $this->response->status);
        self::assertSame(ContentTypeEnum::PROBLEM_JSON->value, $this->response->headers[HeaderNameEnum::CONTENT_TYPE->value]);
        self::assertSame([
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => self::DETAIL,
        ], $this->response->json());
    }

    public function testStatusWritesPlainStatusLine(): void
    {
        $this->responder->status(ResponseStatusEnum::BAD_REQUEST);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, $this->response->status);
        self::assertSame(self::BAD_REQUEST_BODY, $this->response->body);
    }

    public function testErrorAnswersApiRequestsWithProblemsAndPagesWithPlainStatus(): void
    {
        $page = new RecordingResponseWriter();

        $this->responder->error(new Request(HttpMethodEnum::GET->value, '/api/v1/products'), ResponseStatusEnum::NOT_FOUND, self::DETAIL);
        new Responder($page)->error(new Request(HttpMethodEnum::GET->value, '/login'), ResponseStatusEnum::NOT_FOUND, self::DETAIL);

        self::assertSame(self::DETAIL, $this->response->json()['detail']);
        self::assertSame('404 Not Found', $page->body);
    }

    public function testWithoutBodyWritesOnlyTheStatus(): void
    {
        $this->responder->withoutBody(ResponseStatusEnum::NO_CONTENT);

        self::assertSame(ResponseStatusEnum::NO_CONTENT->value, $this->response->status);
        self::assertSame('', $this->response->body);
    }

    public function testPermanentRedirectPointsToTheNewLocation(): void
    {
        $this->responder->permanentRedirect(self::NEW_LOCATION);

        self::assertSame(ResponseStatusEnum::MOVED_PERMANENTLY->value, $this->response->status);
        self::assertSame(self::NEW_LOCATION, $this->response->headers[HeaderNameEnum::LOCATION->value]);
    }
}
