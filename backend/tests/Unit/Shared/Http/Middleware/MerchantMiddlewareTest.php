<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http\Middleware;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\Middleware\MerchantMiddleware;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\SessionUserInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\RecordingHandler;
use Tests\Unit\Support\RecordingResponseWriter;

final class MerchantMiddlewareTest extends TestCase
{
    private const string PATH = '/api/v1/panel/orders';
    private const string SIGN_IN_DETAIL = 'Sign in to continue.';
    private const string NO_ACCESS_DETAIL = 'Your account has no access to the merchant panel.';

    private RecordingResponseWriter $response;

    protected function setUp(): void
    {
        $this->response = new RecordingResponseWriter();
    }


    public function testLetsMerchantsThrough(): void
    {
        $handler = new RecordingHandler();

        $this->process(true, true, $handler);

        self::assertCount(1, $handler->handled);
    }

    public function testAsksAnonymousVisitorsToSignIn(): void
    {
        $handler = new RecordingHandler();

        $output = $this->process(false, false, $handler);

        self::assertSame([], $handler->handled);
        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, $this->response->status);
        self::assertStringContainsString(self::SIGN_IN_DETAIL, $output);
    }

    public function testRefusesSignedInUsersWithoutMerchantAccess(): void
    {
        $handler = new RecordingHandler();

        $output = $this->process(true, false, $handler);

        self::assertSame([], $handler->handled);
        self::assertSame(ResponseStatusEnum::FORBIDDEN->value, $this->response->status);
        self::assertStringContainsString(self::NO_ACCESS_DETAIL, $output);
    }

    private function process(bool $signedIn, bool $merchant, RecordingHandler $handler): string
    {
        $user = $this->createStub(SessionUserInterface::class);
        $user->method('isSignedIn')->willReturn($signedIn);
        $user->method('isMerchant')->willReturn($merchant);

        new MerchantMiddleware($user, new Responder($this->response))->process(new Request(HttpMethodEnum::GET->value, self::PATH), $handler);

        return $this->response->body;
    }
}
