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
    private const string TOKEN = 'confirmation-token';
    private const string API_PATH = '/api/v1/email-confirmations';

    public function testReadsTheTokenFromJson(): void
    {
        $request = new Request(HttpMethodEnum::POST->value, self::API_PATH, body: '{"token":"' . self::TOKEN . '"}');

        self::assertSame(self::TOKEN, ConfirmationTokenRequest::fromJson($request)->token);
    }

    public function testRejectsJsonWithoutAToken(): void
    {
        $this->expectException(InvalidRequestException::class);

        ConfirmationTokenRequest::fromJson(new Request(HttpMethodEnum::POST->value, self::API_PATH, body: '{"token":""}'));
    }
}
