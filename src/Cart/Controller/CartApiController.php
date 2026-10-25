<?php

declare(strict_types=1);

namespace App\Cart\Controller;

use App\Cart\Response\CartResponse;
use App\Cart\Request\SetCartItemQuantityRequest;
use App\Cart\Entity\CartId;
use App\Cart\Exception\CartNotFoundException;
use App\Cart\Exception\CartNotOpenException;
use App\Cart\Exception\InvalidCartQuantityException;
use App\Cart\Service\CartService;
use App\Catalog\Exception\ProductNotFoundException;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\MapException;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;

final class CartApiController
{
    private const string CARTS_ROUTE_PATH = '/api/v1/carts';
    private const string CART_ROUTE_PATH = self::CARTS_ROUTE_PATH . '/{id:' . CartId::PATTERN . '}';
    private const string CART_ITEM_ROUTE_PATH = self::CART_ROUTE_PATH . '/items/{productId:\d+}';
    private const string CART_LOCATION_FORMAT = self::CARTS_ROUTE_PATH . '/%s';
    private const string ID_PARAMETER = 'id';

    public function __construct(
        private readonly CartService $carts,
        private readonly Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::POST, self::CARTS_ROUTE_PATH)]
    public function create(): void
    {
        $cart = $this->carts->create();

        $this->responder->created(CartResponse::fromCart($cart), sprintf(self::CART_LOCATION_FORMAT, $cart->id->value));
    }

    #[Route(HttpMethodEnum::GET, self::CART_ROUTE_PATH)]
    #[MapException(CartNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    public function show(Request $request): void
    {
        $cart = $this->carts->get(CartId::fromString((string) $request->routeParameter(self::ID_PARAMETER)));

        $this->responder->json(CartResponse::fromCart($cart));
    }

    #[Route(HttpMethodEnum::PUT, self::CART_ITEM_ROUTE_PATH)]
    #[MapException(CartNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    #[MapException(ProductNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    #[MapException(CartNotOpenException::class, ResponseStatusEnum::CONFLICT)]
    #[MapException(InvalidCartQuantityException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    public function setItemQuantity(Request $request): void
    {
        $command = SetCartItemQuantityRequest::fromRequest($request);
        $cart = $this->carts->setItemQuantity($command->cartId, $command->productId, $command->quantity);

        $this->responder->json(CartResponse::fromCart($cart));
    }
}
