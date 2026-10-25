<?php

declare(strict_types=1);

namespace App\Model;

final class HomeModel implements HomeModelInterface
{
    private const string MESSAGE = 'PHP MVC playground is running.';

    public function message(): string
    {
        return self::MESSAGE;
    }
}
