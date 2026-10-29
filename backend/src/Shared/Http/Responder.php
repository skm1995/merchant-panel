<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Contract\Arrayable;
use App\Shared\Http\Enum\CharacterEncodingEnum;
use App\Shared\Http\Enum\ContentTypeEnum;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use Closure;

/**
 * Writes the application's responses: JSON, problem details, redirects, statuses, and the one
 * server-rendered view, all through the response writer.
 */
final readonly class Responder
{
    private const string VIEW_PATH_FORMAT = '%s/views/%s.php';
    private const string STATUS_BODY_FORMAT = '%d %s';
    private const string PROBLEM_TYPE_KEY = 'type';
    private const string PROBLEM_TITLE_KEY = 'title';
    private const string PROBLEM_STATUS_KEY = 'status';
    private const string PROBLEM_DETAIL_KEY = 'detail';
    private const string DEFAULT_PROBLEM_TYPE = 'about:blank';
    // Floats keep their fraction (2.0, not 2), so a number field never changes its JSON type.
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    public function __construct(private ResponseWriterInterface $writer)
    {
    }

    /**
     * Renders a PHP view from views/, which reads its data from $page and escapes every value
     * it prints with $e.
     *
     * @param array<string, mixed> $page
     */
    public function render(string $view, array $page = []): void
    {
        $this->writer->writeHeader(HeaderNameEnum::CONTENT_TYPE, ContentTypeEnum::HTML->value);
        $viewPath = sprintf(self::VIEW_PATH_FORMAT, dirname(__DIR__, 3), $view);

        ob_start();

        try {
            (static function (string $viewPath, array $page, Closure $e): void {
                require $viewPath;
            })($viewPath, $page, self::escape(...));
        } finally {
            $html = (string) ob_get_clean();
        }

        $this->writer->writeBody($html);
    }

    /**
     * Adds a response header that the other methods do not write, before the body is written.
     */
    public function sendHeader(HeaderNameEnum $name, string $value): void
    {
        $this->writer->writeHeader($name, $value);
    }

    public function json(
        Arrayable $body,
        ResponseStatusEnum $status = ResponseStatusEnum::OK,
        ?EntityTag $entityTag = null,
    ): void {
        if ($entityTag !== null) {
            $this->writer->writeHeader(HeaderNameEnum::ETAG, $entityTag->value);
        }

        $this->writeJson($body->toArray(), $status, ContentTypeEnum::JSON);
    }

    public function created(Arrayable $body, string $location, ?EntityTag $entityTag = null): void
    {
        $this->writer->writeHeader(HeaderNameEnum::LOCATION, $location);
        $this->json($body, ResponseStatusEnum::CREATED, $entityTag);
    }

    /**
     * Writes an RFC 9457 problem details response.
     */
    public function problem(ResponseStatusEnum $status, string $detail): void
    {
        $this->writeJson([
            self::PROBLEM_TYPE_KEY => self::DEFAULT_PROBLEM_TYPE,
            self::PROBLEM_TITLE_KEY => $status->message(),
            self::PROBLEM_STATUS_KEY => $status->value,
            self::PROBLEM_DETAIL_KEY => $detail,
        ], $status, ContentTypeEnum::PROBLEM_JSON);
    }

    /**
     * Sends clients of a retired URL to its replacement for good.
     */
    public function permanentRedirect(string $location): void
    {
        $this->writer->writeStatus(ResponseStatusEnum::MOVED_PERMANENTLY);
        $this->writer->writeHeader(HeaderNameEnum::LOCATION, $location);
    }

    /**
     * Writes a status that needs no body, such as 202 Accepted or 204 No Content.
     */
    public function withoutBody(ResponseStatusEnum $status): void
    {
        $this->writer->writeStatus($status);
    }

    /**
     * Answers a rejected request: API clients get problem details, browsers a plain status.
     */
    public function error(Request $request, ResponseStatusEnum $status, string $detail): void
    {
        if ($request->isApi()) {
            $this->problem($status, $detail);

            return;
        }

        $this->status($status);
    }

    public function status(ResponseStatusEnum $status): void
    {
        $this->writer->writeStatus($status);
        $this->writer->writeHeader(HeaderNameEnum::CONTENT_TYPE, ContentTypeEnum::PLAIN->value);
        $this->writer->writeBody(sprintf(self::STATUS_BODY_FORMAT, $status->value, $status->message()));
    }

    /** @param array<string, mixed> $data */
    private function writeJson(array $data, ResponseStatusEnum $status, ContentTypeEnum $contentType): void
    {
        $this->writer->writeStatus($status);
        $this->writer->writeHeader(HeaderNameEnum::CONTENT_TYPE, $contentType->value);
        $this->writer->writeBody(json_encode($data, self::JSON_FLAGS));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, CharacterEncodingEnum::UTF8->value);
    }
}
