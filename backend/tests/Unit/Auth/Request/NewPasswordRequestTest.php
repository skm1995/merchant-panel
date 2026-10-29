<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Request;

use App\Auth\Request\NewPasswordRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NewPasswordRequestTest extends TestCase
{
    private const string API_PATH = '/api/v1/password-reset-confirmations';

    public function testReadsTheTokenAndPasswordFromJson(): void
    {
        $request = new Request(HttpMethodEnum::POST->value, self::API_PATH, body: '{"token":"reset-token","password":"new-password"}');

        $newPassword = NewPasswordRequest::fromJson($request);

        self::assertSame('reset-token', $newPassword->token);
        self::assertSame('new-password', $newPassword->password);
    }

    #[DataProvider('incompleteBodies')]
    public function testRejectsJsonWithoutATokenAndAPassword(string $body): void
    {
        $this->expectException(InvalidRequestException::class);

        NewPasswordRequest::fromJson(new Request(HttpMethodEnum::POST->value, self::API_PATH, body: $body));
    }

    /** @return iterable<string, array{string}> */
    public static function incompleteBodies(): iterable
    {
        yield 'no token' => ['{"password":"new-password"}'];
        yield 'empty token' => ['{"token":"","password":"new-password"}'];
        yield 'no password' => ['{"token":"reset-token"}'];
        yield 'password not a string' => ['{"token":"reset-token","password":12345678}'];
    }
}
