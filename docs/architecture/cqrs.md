# CQRS: separate paths for changing and reading orders and payments

Command query responsibility segregation (CQRS) means that code changing state and code
reading it take different paths, with different models. The project uses it in two features,
[`Order`](../../backend/src/Order) and [`Payment`](../../backend/src/Payment), and on purpose
nowhere else ([0022](../decisions/0022-cqrs-only-for-orders-and-payments.md)).

## The two paths

```mermaid
flowchart LR
    subgraph Write["Write path: a command"]
        C[Controller] -->|PlaceOrderCommand| B[command bus]
        B --> H[PlaceOrderHandler]
        H -->|transaction| R[OrderRepository<br/>entities, locks,<br/>conditional updates]
        H -->|same transaction| O[(outbox)]
        H -->|returns ids| B
        B --> C
    end
    subgraph Read["Read path: a query"]
        C2[Controller] -->|GetOrderQuery| QB[query bus]
        QB --> Q[GetOrderHandler]
        Q --> RR[OrderReadRepository<br/>SQL straight into<br/>read models]
        Q -->|OrderView| QB
        QB --> C2
    end
    R --> DB[(MySQL)]
    RR --> DB
```

| | Write path | Read path |
|---|---|---|
| Input | A command in `Command/`, such as [`PlaceOrderCommand`](../../backend/src/Order/Command/PlaceOrderCommand.php) | A query in `Query/`, such as [`GetOrderQuery`](../../backend/src/Order/Query/GetOrderQuery.php) |
| Handler | [`PlaceOrderHandler`](../../backend/src/Order/Command/PlaceOrderHandler.php), [`CancelOrderHandler`](../../backend/src/Order/Command/CancelOrderHandler.php), [`StartPaymentHandler`](../../backend/src/Payment/Command/StartPaymentHandler.php), [`HandlePaymentWebhookHandler`](../../backend/src/Payment/Command/HandlePaymentWebhookHandler.php) | [`GetOrderHandler`](../../backend/src/Order/Query/GetOrderHandler.php), [`ListOrdersHandler`](../../backend/src/Order/Query/ListOrdersHandler.php), [`GetPaymentHandler`](../../backend/src/Payment/Query/GetPaymentHandler.php) |
| Model | Entities that enforce rules, such as [`Order`](../../backend/src/Order/Entity/Order.php) and `OrderState` with its version | Read models shaped for the response, such as [`OrderView`](../../backend/src/Order/ReadModel/OrderView.php) and [`OrderSummaryPage`](../../backend/src/Order/ReadModel/OrderSummaryPage.php) |
| Repository | [`OrderRepositoryInterface`](../../backend/src/Order/Repository/OrderRepositoryInterface.php): locks, conditional updates, inserts | [`OrderReadRepositoryInterface`](../../backend/src/Order/Repository/OrderReadRepositoryInterface.php): `SELECT`s only |
| Transaction | Always, with an explicit isolation level, retried after a deadlock | None; one consistent read |
| Returns | An identifier or an outcome (the new order's id and access token, a payment id, a webhook's `processed` or `duplicate`), never a read model | A read model the API serializes as is |

A controller is the only place both meet: checkout sends `PlaceOrderCommand`, which returns the
new order's id and access token, then asks `GetOrderQuery` to answer with the order as any
later read would see it.

## The buses

Controllers do not know the handlers. They send commands through
[`CommandBusInterface`](../../backend/src/Shared/Messaging/Bus/CommandBusInterface.php) and
queries through [`QueryBusInterface`](../../backend/src/Shared/Messaging/Bus/QueryBusInterface.php),
two Symfony Messenger buses ([0023](../decisions/0023-commands-and-queries-through-messenger-buses.md)):

```php
$placed = $this->commands->dispatch(PlaceOrderRequest::toCommand($request)); // PlaceOrderResult
$order = $this->queries->ask(new GetOrderQuery($placed->orderId, $placed->accessToken)); // OrderView
```

- **Handlers are found by name.** [`ConventionHandlersLocator`](../../backend/src/Shared/Messaging/Bus/ConventionHandlersLocator.php)
  sends `PlaceOrderCommand` to `PlaceOrderHandler` in the same namespace and builds it from
  the container only then. There is no registry to keep in step, and a command without a
  handler fails with the name of the class it is missing.
- **Results are typed.** A command or query declares what its handler returns, such as
  `@implements CommandInterface<PlaceOrderResult>`, and PHPStan checks every call site.
- **Exceptions are not wrapped.** Messenger wraps a handler's exception in
  `HandlerFailedException`; the buses throw the original, so controllers keep mapping domain
  exceptions to HTTP statuses.
- **Commands are logged.** [`CommandLogMiddleware`](../../backend/src/Shared/Messaging/Bus/CommandLogMiddleware.php)
  logs `Command handled` with the duration, or `Command failed` with the reason, such as
  insufficient stock. Queries change nothing and are not logged.
- **Transactions stay in the handlers.** Each handler chooses its isolation level, which a
  bus-wide transaction could not.

### Now or later

A command class says when it runs:

| Declared as | Runs | Example |
|---|---|---|
| `implements CommandInterface<TResult>` | **now**, through the command bus, in the request; the caller gets the result or the exception | `PlaceOrderCommand`, `CancelOrderCommand`, `StartPaymentCommand`, `HandlePaymentWebhookCommand` |
| `implements OutboxMessageInterface` with `#[Message(transport: ...)]` | **later**, recorded in the outbox in the caller's transaction, then delivered by the relay and the worker | `ExpireOrderReservationCommand` |

A later command goes through the outbox rather than straight to RabbitMQ: a message sent to the
broker inside a transaction that rolls back would act on an order that does not exist, and one
sent after the commit is lost when the broker is down. The outbox row commits or rolls back
with the order ([0006](../decisions/0006-transactional-outbox.md)).

## Why the paths differ

The write path protects invariants under concurrency. Placing an order locks the cart and the
products in a fixed order, reserves stock with conditional updates, records the order and an
outbox message in one transaction, and accepts a repeated `Idempotency-Key`
([checkout flow](../flows/checkout.md)). That code needs entities, versions, and locks, and it
should not be shaped by what a page wants to show.

The read path only shapes data. An order page needs the order with its lines and totals; the
panel's list needs a page of summaries with a cursor. Reading them through entities would load
rules and state that a read never uses, so read repositories map SQL straight into read
models, and their queries can be tuned for the screen, for example with
[cursor pagination](../flows/panel.md#orders).

## Commands that arrive later

Some commands are not sent by a controller but delivered by the worker:
[`ExpireOrderReservationCommand`](../../backend/src/Order/Command/ExpireOrderReservationCommand.php)
is recorded in the outbox at checkout and handled by
[`ExpireOrderReservationHandler`](../../backend/src/Order/Command/ExpireOrderReservationHandler.php)
when the reservation ends. The same rules apply: a transaction, a conditional update on the
order's version, and no harm when the message is delivered twice
([messaging](backend.md#messaging)).

## What CQRS here is not

- **No separate read database.** Both paths use the same MySQL tables, so a query sees a
  command's result as soon as it commits; there is no eventual consistency to explain to a
  user. The only derived data is the [daily sales summary](../decisions/0017-daily-sales-summary-recomputed-per-day.md)
  for reports, which the worker refreshes about a second after a payment.
- **No event sourcing.** State is stored as rows, not rebuilt from a log of events.
- **No asynchronous bus for requests.** Commands sent by a controller run at once; only work
  that may run later goes through the outbox.

## Where it is not used

[Black Friday simulations](../flows/black-friday.md) use the same buses: a synchronous
`StartSimulationCommand`, an asynchronous `RunSimulationCommand` delivered to the simulator,
and a `GetCurrentSimulationQuery`.

The catalog, carts, accounts, and reports are plain application services, such as
[`CartService`](../../backend/src/Cart/Service/CartService.php) and
[`ReportService`](../../backend/src/Report/Service/ReportService.php). Their reads and writes
have the same shape, or they only read, so a second path would be code without a purpose.
[`docs/patterns.md`](../patterns.md#cqrs) has the rule for new features.

## Tests

Handlers that make decisions have unit tests of their outcomes: an order that cannot be
cancelled, a stale version, a duplicate webhook that changes nothing. The functional tests
drive both paths through the API, including concurrent checkouts and concurrent webhook
deliveries ([testing](../operations/testing.md)).
