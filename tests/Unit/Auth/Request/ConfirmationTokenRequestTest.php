<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Request;

use App\Auth\Request\ConfirmationTokenRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;

final class ConfirmationTokenRequestTest extends TestCase
{
    private const string PATH = '/confirm';
    private const string TOKEN = 'confirmation-token';

    public function testReadsTokenFromLinkQuery(): void
    {
        $request = new Request(HttpMethodEnum::GET->value, self::PATH, ['token' => self::TOKEN]);

        self::assertSame(self::TOKEN, ConfirmationTokenRequest::fromLink($request)->token);
    }

    public function testReadsTokenFromSubmittedForm(): void
    {
        $request = new Request(HttpMethodEnum::POST->value, self::PATH, [], ['token' => self::TOKEN]);

        self::assertSame(self::TOKEN, ConfirmationTokenRequest::fromForm($request)->token);
    }

    public function testRejectsMissingToken(): void
    {
        $this->expectException(InvalidRequestException::class);

        ConfirmationTokenRequest::fromForm(new Request(HttpMethodEnum::POST->value, self::PATH));
    }
}
