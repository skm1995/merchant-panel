<?php

declare(strict_types=1);

namespace App\Shared\Http;

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

    public function __construct(private readonly CsrfToken $csrfToken)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = []): void
    {
        self::header(HeaderNameEnum::CONTENT_TYPE, ContentTypeEnum::HTML->value);
        $data[self::CSRF_FIELD_DATA_KEY] = CsrfToken::FIELD_NAME;
        $data[self::CSRF_TOKEN_DATA_KEY] = $this->csrfToken->get();
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

    public function status(ResponseStatusEnum $status): void
    {
        http_response_code($status->value);
        self::header(HeaderNameEnum::CONTENT_TYPE, ContentTypeEnum::PLAIN->value);
        echo sprintf(self::STATUS_BODY_FORMAT, $status->value, $status->message());
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
