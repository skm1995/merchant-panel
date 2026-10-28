<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

interface QueryBusInterface
{
    /**
     * Answers the query at once with its handler's read model; the handler's exceptions reach
     * the caller unchanged.
     *
     * @template TResult
     * @param QueryInterface<TResult> $query
     * @return TResult
     */
    public function ask(QueryInterface $query): mixed;
}
