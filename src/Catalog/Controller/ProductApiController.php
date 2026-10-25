<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Request\ListProductsRequest;
use App\Catalog\Response\ProductPageResponse;
use App\Catalog\Response\ProductResponse;
use App\Catalog\Exception\InvalidPageSizeException;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Service\ProductCatalog;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\MapException;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;

final class ProductApiController
{
    private const string PRODUCTS_ROUTE_PATH = '/api/v1/products';
    private const string PRODUCT_ROUTE_PATH = '/api/v1/products/{id:\d+}';
    private const string ID_PARAMETER = 'id';

    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::GET, self::PRODUCTS_ROUTE_PATH)]
    #[MapException(InvalidPageSizeException::class, ResponseStatusEnum::BAD_REQUEST)]
    public function list(Request $request): void
    {
        $query = ListProductsRequest::fromRequest($request);
        $page = $this->catalog->page($query->limit, $query->cursor);

        $this->responder->json(ProductPageResponse::fromPage($page));
    }

    #[Route(HttpMethodEnum::GET, self::PRODUCT_ROUTE_PATH)]
    #[MapException(ProductNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    public function show(Request $request): void
    {
        $product = $this->catalog->product((int) $request->routeParameter(self::ID_PARAMETER));

        $this->responder->json(ProductResponse::fromProduct($product));
    }
}
