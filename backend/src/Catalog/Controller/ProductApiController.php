<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Entity\Product;
use App\Catalog\Exception\InvalidPageSizeException;
use App\Catalog\Exception\ProductNotFoundException;
use App\Catalog\Exception\ProductVersionMismatchException;
use App\Catalog\Exception\StockBelowReservationsException;
use App\Catalog\Request\EditProductRequest;
use App\Catalog\Request\ListProductsRequest;
use App\Catalog\Response\ProductDetailsResponse;
use App\Catalog\Response\ProductPageResponse;
use App\Catalog\Response\ProductResponse;
use App\Catalog\Service\ProductCatalog;
use App\Catalog\Service\ProductEditService;
use App\Shared\Http\EntityTag;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\MapException;
use App\Shared\Http\Request;
use App\Shared\Http\RequireSignedIn;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;

final readonly class ProductApiController
{
    private const string PRODUCTS_ROUTE_PATH = '/api/v1/products';
    private const string PRODUCT_ROUTE_PATH = '/api/v1/products/{id:\d+}';
    private const string PANEL_PRODUCT_ROUTE_PATH = '/api/v1/panel/products/{id:\d+}';
    private const string ID_PARAMETER = 'id';

    public function __construct(
        private ProductCatalog $catalog,
        private ProductEditService $editor,
        private Responder $responder,
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

    #[Route(HttpMethodEnum::GET, self::PANEL_PRODUCT_ROUTE_PATH)]
    #[RequireSignedIn]
    #[MapException(ProductNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    public function showForEditing(Request $request): void
    {
        $this->respondWithDetails($this->catalog->product((int) $request->routeParameter(self::ID_PARAMETER)));
    }

    #[Route(HttpMethodEnum::PATCH, self::PANEL_PRODUCT_ROUTE_PATH)]
    #[RequireSignedIn]
    #[MapException(ProductNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    #[MapException(ProductVersionMismatchException::class, ResponseStatusEnum::PRECONDITION_FAILED)]
    #[MapException(StockBelowReservationsException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    public function edit(Request $request): void
    {
        $edit = EditProductRequest::fromRequest($request);

        $this->respondWithDetails($this->editor->edit($edit->productId, $edit->price, $edit->stock, $edit->expectedVersion));
    }

    private function respondWithDetails(Product $product): void
    {
        $this->responder->json(ProductDetailsResponse::fromProduct($product), entityTag: EntityTag::fromVersion($product->version));
    }
}
