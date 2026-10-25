<?php

declare(strict_types=1);

namespace App\Order\Controller;

use App\Cart\Exception\CartNotFoundException;
use App\Cart\Exception\CartNotOpenException;
use App\Cart\Exception\EmptyCartException;
use App\Catalog\Exception\InsufficientStockException;
use App\Order\Command\PlaceOrderHandler;
use App\Order\Entity\OrderId;
use App\Order\Exception\IdempotencyKeyReusedException;
use App\Order\Exception\OrderNotFoundException;
use App\Order\Query\GetOrderHandler;
use App\Order\Query\GetOrderQuery;
use App\Order\Request\PlaceOrderRequest;
use App\Order\Response\OrderResponse;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\MapException;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;
use App\Shared\Identifier\RandomUuid;

final class OrderApiController
{
    private const string CHECKOUT_ROUTE_PATH = '/api/v1/carts/{id:' . RandomUuid::PATTERN . '}/checkout';
    private const string ORDER_ROUTE_PATH = '/api/v1/orders/{id:' . RandomUuid::PATTERN . '}';
    private const string ORDER_LOCATION_FORMAT = '/api/v1/orders/%s';
    private const string ID_PARAMETER = 'id';

    public function __construct(
        private readonly PlaceOrderHandler $placeOrder,
        private readonly GetOrderHandler $getOrder,
        private readonly Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::POST, self::CHECKOUT_ROUTE_PATH)]
    #[MapException(CartNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    #[MapException(CartNotOpenException::class, ResponseStatusEnum::CONFLICT)]
    #[MapException(InsufficientStockException::class, ResponseStatusEnum::CONFLICT)]
    #[MapException(EmptyCartException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    #[MapException(IdempotencyKeyReusedException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    public function checkout(Request $request): void
    {
        $orderId = $this->placeOrder->handle(PlaceOrderRequest::fromRequest($request)->toCommand());
        $order = $this->getOrder->handle(new GetOrderQuery($orderId));

        $this->responder->created(OrderResponse::fromView($order), sprintf(self::ORDER_LOCATION_FORMAT, $orderId->value));
    }

    #[Route(HttpMethodEnum::GET, self::ORDER_ROUTE_PATH)]
    #[MapException(OrderNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    public function show(Request $request): void
    {
        $orderId = OrderId::fromString((string) $request->routeParameter(self::ID_PARAMETER));

        $this->responder->json(OrderResponse::fromView($this->getOrder->handle(new GetOrderQuery($orderId))));
    }
}
