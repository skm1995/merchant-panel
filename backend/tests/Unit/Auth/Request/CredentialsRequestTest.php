<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Request;

use App\Auth\Request\CredentialsRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;

final class CredentialsRequestTest extends TestCase
{
    private const string API_PATH = '/api/v1/session';
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';

    public function testReadsEmailAndPasswordFromJson(): void
    {
        $credentials = CredentialsRequest::fromJson(self::json('{"email":"person@example.com","password":"correct-horse-battery"}'));

        self::assertSame(self::EMAIL, $credentials->email);
        self::assertSame(self::PASSWORD, $credentials->password);
    }

    public function testRejectsJsonWithoutTwoStrings(): void
    {
        $this->expectException(InvalidRequestException::class);

        CredentialsRequest::fromJson(self::json('{"email":"person@example.com","password":12345678}'));
    }

    private static function json(string $body): Request
    {
        return new Request(HttpMethodEnum::POST->value, self::API_PATH, body: $body);
    }
}
