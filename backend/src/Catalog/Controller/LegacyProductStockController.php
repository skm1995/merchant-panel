<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;

/**
 * Keeps links to the retired product stock page working by sending them to the product
 * editor of the merchant panel. Only a product id is accepted, never SQL.
 */
final readonly class LegacyProductStockController
{
    private const string ROUTE_PATH = '/legacy/product-stock.php';
    private const string ID_PARAMETER = 'id';
    private const string PRODUCT_ID_PATTERN = '/^[1-9]\d*$/';
    private const string EDITOR_PATH_FORMAT = '/products/%s/edit';
    private const string UNKNOWN_PRODUCT_DETAIL = 'The link does not name a product.';

    public function __construct(private Responder $responder)
    {
    }

    #[Route(HttpMethodEnum::GET, self::ROUTE_PATH)]
    public function redirect(Request $request): void
    {
        $productId = $request->query(self::ID_PARAMETER);

        if ($productId === null || preg_match(self::PRODUCT_ID_PATTERN, $productId) !== 1) {
            $this->responder->error($request, ResponseStatusEnum::NOT_FOUND, self::UNKNOWN_PRODUCT_DETAIL);

            return;
        }

        $this->responder->permanentRedirect(sprintf(self::EDITOR_PATH_FORMAT, $productId));
    }
}
