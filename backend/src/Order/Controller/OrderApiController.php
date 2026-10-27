<?php

declare(strict_types=1);

namespace App\Order\Controller;

use App\Cart\Entity\CartId;
use App\Cart\Exception\CartNotFoundException;
use App\Cart\Exception\CartNotOpenException;
use App\Cart\Exception\EmptyCartException;
use App\Catalog\Exception\InsufficientStockException;
use App\Order\Command\CancelOrderHandler;
use App\Order\Command\PlaceOrderHandler;
use App\Order\Entity\OrderId;
use App\Order\Enum\OrderRateLimitEnum;
use App\Order\Exception\IdempotencyKeyReusedException;
use App\Order\Exception\InvalidOrderTransitionException;
use App\Order\Exception\OrderNotFoundException;
use App\Order\Exception\OrderVersionMismatchException;
use App\Order\Query\GetOrderHandler;
use App\Order\Query\GetOrderQuery;
use App\Order\Query\ListOrdersHandler;
use App\Order\ReadModel\OrderView;
use App\Order\Request\CancelOrderRequest;
use App\Order\Request\ListOrdersRequest;
use App\Order\Request\PlaceOrderRequest;
use App\Order\Response\OrderResponse;
use App\Order\Response\OrderSummaryPageResponse;
use App\Shared\Http\EntityTag;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\MapException;
use App\Shared\Http\RateLimit;
use App\Shared\Http\Request;
use App\Shared\Http\RequireMerchant;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;

final readonly class OrderApiController
{
    private const string CHECKOUT_ROUTE_PATH = '/api/v1/carts/{id:' . CartId::PATTERN . '}/checkout';
    private const string ORDER_ROUTE_PATH = '/api/v1/orders/{id:' . OrderId::PATTERN . '}';
    private const string CANCEL_ROUTE_PATH = self::ORDER_ROUTE_PATH . '/cancel';
    private const string ORDER_LOCATION_FORMAT = '/api/v1/orders/%s';
    private const string PANEL_ORDERS_ROUTE_PATH = '/api/v1/panel/orders';
    private const string ID_PARAMETER = 'id';

    public function __construct(
        private PlaceOrderHandler $placeOrder,
        private GetOrderHandler $getOrder,
        private CancelOrderHandler $cancelOrder,
        private ListOrdersHandler $listOrders,
        private Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::POST, self::CHECKOUT_ROUTE_PATH)]
    #[RateLimit(OrderRateLimitEnum::CHECKOUT->value)]
    #[MapException(CartNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    #[MapException(CartNotOpenException::class, ResponseStatusEnum::CONFLICT)]
    #[MapException(InsufficientStockException::class, ResponseStatusEnum::CONFLICT)]
    #[MapException(EmptyCartException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    #[MapException(IdempotencyKeyReusedException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    public function checkout(Request $request): void
    {
        $orderId = $this->placeOrder->handle(PlaceOrderRequest::toCommand($request));
        $order = $this->getOrder->handle(new GetOrderQuery($orderId));

        $this->responder->created(
            OrderResponse::fromView($order),
            sprintf(self::ORDER_LOCATION_FORMAT, $orderId->value),
            self::entityTag($order),
        );
    }

    #[Route(HttpMethodEnum::GET, self::ORDER_ROUTE_PATH)]
    #[MapException(OrderNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    public function show(Request $request): void
    {
        $orderId = OrderId::fromString($request->routeParameter(self::ID_PARAMETER));
        $order = $this->getOrder->handle(new GetOrderQuery($orderId));

        $this->responder->json(OrderResponse::fromView($order), entityTag: self::entityTag($order));
    }

    #[Route(HttpMethodEnum::POST, self::CANCEL_ROUTE_PATH)]
    #[MapException(OrderNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    #[MapException(OrderVersionMismatchException::class, ResponseStatusEnum::PRECONDITION_FAILED)]
    #[MapException(InvalidOrderTransitionException::class, ResponseStatusEnum::CONFLICT)]
    public function cancel(Request $request): void
    {
        $command = CancelOrderRequest::toCommand($request);
        $this->cancelOrder->handle($command);
        $order = $this->getOrder->handle(new GetOrderQuery($command->orderId));

        $this->responder->json(OrderResponse::fromView($order), entityTag: self::entityTag($order));
    }

    #[Route(HttpMethodEnum::GET, self::PANEL_ORDERS_ROUTE_PATH)]
    #[RequireMerchant]
    public function list(Request $request): void
    {
        $this->responder->json(OrderSummaryPageResponse::fromPage($this->listOrders->handle(ListOrdersRequest::toQuery($request))));
    }

    private static function entityTag(OrderView $order): EntityTag
    {
        return EntityTag::fromVersion($order->version);
    }
}
