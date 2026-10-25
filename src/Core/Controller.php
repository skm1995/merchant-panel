<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    private const string VIEW_PATH_FORMAT = '%s/views/%s.php';

    /** @param array<string, mixed> $data */
    protected function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require sprintf(self::VIEW_PATH_FORMAT, dirname(__DIR__, 2), $view);
    }
}
