<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Contract\Arrayable;
use App\Shared\Http\Enum\CharacterEncodingEnum;
use App\Shared\Http\Enum\ContentTypeEnum;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;

final class Responder
{
    private const string VIEW_PATH_FORMAT = '%s/views/%s.php';
    private const string HEADER_FORMAT = '%s: %s';
    private const string STATUS_BODY_FORMAT = '%d %s';
    private const string ESCAPE_DATA_KEY = 'e';
    private const string PROBLEM_TYPE_KEY = 'type';
    private const string PROBLEM_TITLE_KEY = 'title';
    private const string PROBLEM_STATUS_KEY = 'status';
    private const string PROBLEM_DETAIL_KEY = 'detail';
    private const string DEFAULT_PROBLEM_TYPE = 'about:blank';
    // Floats keep their fraction (2.0, not 2), so a number field never changes its JSON type.
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = []): void
    {
        self::header(HeaderNameEnum::CONTENT_TYPE, ContentTypeEnum::HTML->value);
        $data[self::ESCAPE_DATA_KEY] = self::escape(...);
        $viewPath = sprintf(self::VIEW_PATH_FORMAT, dirname(__DIR__, 3), $view);

        (static function (string $viewPath, array $data): void {
            extract($data, EXTR_SKIP);
            require $viewPath;
        })($viewPath, $data);
    }

    public function json(
        Arrayable $body,
        ResponseStatusEnum $status = ResponseStatusEnum::OK,
        ?EntityTag $entityTag = null,
    ): void {
        if ($entityTag !== null) {
            self::header(HeaderNameEnum::ETAG, $entityTag->value);
        }

        $this->writeJson($body->toArray(), $status, ContentTypeEnum::JSON);
    }

    public function created(Arrayable $body, string $location, ?EntityTag $entityTag = null): void
    {
        self::header(HeaderNameEnum::LOCATION, $location);
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
        http_response_code(ResponseStatusEnum::MOVED_PERMANENTLY->value);
        self::header(HeaderNameEnum::LOCATION, $location);
    }

    /**
     * Writes a status that needs no body, such as 202 Accepted or 204 No Content.
     */
    public function withoutBody(ResponseStatusEnum $status): void
    {
        http_response_code($status->value);
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
        http_response_code($status->value);
        self::header(HeaderNameEnum::CONTENT_TYPE, ContentTypeEnum::PLAIN->value);
        echo sprintf(self::STATUS_BODY_FORMAT, $status->value, $status->message());
    }

    /** @param array<string, mixed> $data */
    private function writeJson(array $data, ResponseStatusEnum $status, ContentTypeEnum $contentType): void
    {
        http_response_code($status->value);
        self::header(HeaderNameEnum::CONTENT_TYPE, $contentType->value);
        echo json_encode($data, self::JSON_FLAGS);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, CharacterEncodingEnum::UTF8->value);
    }

    /**
     * Adds a response header that the other methods do not write, before the body is written.
     */
    public function sendHeader(HeaderNameEnum $name, string $value): void
    {
        self::header($name, $value);
    }

    private static function header(HeaderNameEnum $name, string $value): void
    {
        header(sprintf(self::HEADER_FORMAT, $name->value, $value));
    }
}
