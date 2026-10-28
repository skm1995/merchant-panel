<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

/**
 * A request to change state, sent through the command bus to the handler named after it:
 * PlaceOrderCommand goes to PlaceOrderHandler. TResult is what the handler returns, such as
 * the new order's identifiers, or null.
 *
 * @template-covariant TResult
 */
interface CommandInterface
{
}
