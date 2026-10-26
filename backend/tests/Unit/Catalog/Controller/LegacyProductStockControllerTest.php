<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Controller;

use App\Catalog\Controller\LegacyProductStockController;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\HttpHarness;

final class LegacyProductStockControllerTest extends TestCase
{
    private const string PATH = '/legacy/product-stock.php';

    protected function tearDown(): void
    {
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testSendsOldLinksToTheProductEditor(): void
    {
        HttpHarness::dispatch(new Request(HttpMethodEnum::GET->value, self::PATH, ['id' => '5']), new LegacyProductStockController(HttpHarness::responder()));

        self::assertSame(ResponseStatusEnum::MOVED_PERMANENTLY->value, http_response_code());
    }

    public function testRejectsAnythingButAProductId(): void
    {
        HttpHarness::dispatch(new Request(HttpMethodEnum::GET->value, self::PATH, ['id' => '5 OR 1=1']), new LegacyProductStockController(HttpHarness::responder()));

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, http_response_code());
    }
}
