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
    private const string CSRF_FIELD_DATA_KEY = 'csrfField';
    private const string CSRF_TOKEN_DATA_KEY = 'csrfToken';
    private const string ESCAPE_DATA_KEY = 'e';
    private const string PROBLEM_TYPE_KEY = 'type';
    private const string PROBLEM_TITLE_KEY = 'title';
    private const string PROBLEM_STATUS_KEY = 'status';
    private const string PROBLEM_DETAIL_KEY = 'detail';
    private const string DEFAULT_PROBLEM_TYPE = 'about:blank';
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(private readonly CsrfToken $csrfToken)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = []): void
    {
        self::header(HeaderNameEnum::CONTENT_TYPE, ContentTypeEnum::HTML->value);
        $data[self::CSRF_FIELD_DATA_KEY] = CsrfToken::FIELD_NAME;
        $data[self::CSRF_TOKEN_DATA_KEY] = $this->csrfToken->getOrCreate();
        $data[self::ESCAPE_DATA_KEY] = self::escape(...);
        $viewPath = sprintf(self::VIEW_PATH_FORMAT, dirname(__DIR__, 3), $view);

        (static function (string $viewPath, array $data): void {
            extract($data, EXTR_SKIP);
            require $viewPath;
        })($viewPath, $data);
    }

    public function redirect(string $path): void
    {
        http_response_code(ResponseStatusEnum::SEE_OTHER->value);
        self::header(HeaderNameEnum::LOCATION, $path);
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

    private static function header(HeaderNameEnum $name, string $value): void
    {
        header(sprintf(self::HEADER_FORMAT, $name->value, $value));
    }
}
