<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Http\Enum\ContentTypeEnum;
use App\Shared\Http\Enum\HeaderNameEnum;

abstract class Controller
{
    private const string VIEW_PATH_FORMAT = '%s/views/%s.php';
    private const string CSRF_DATA_KEY = 'csrfToken';

    public function __construct(private readonly CsrfToken $csrfToken)
    {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $view, array $data = []): void
    {
        header(HeaderNameEnum::CONTENT_TYPE->value . ': ' . ContentTypeEnum::HTML->value);
        $data[self::CSRF_DATA_KEY] = $this->csrfToken->get();
        extract($data, EXTR_SKIP);
        $viewPath = dirname(__DIR__, 3) |> (
            static fn (string $projectRoot): string =>
            sprintf(self::VIEW_PATH_FORMAT, $projectRoot, $view)
        );
        require $viewPath;
    }
}
