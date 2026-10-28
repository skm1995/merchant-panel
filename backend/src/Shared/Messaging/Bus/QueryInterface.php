<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

/**
 * A request to read, sent through the query bus to the handler named after it: GetOrderQuery
 * goes to GetOrderHandler. TResult is the read model the handler returns.
 *
 * @template-covariant TResult
 */
interface QueryInterface
{
}
