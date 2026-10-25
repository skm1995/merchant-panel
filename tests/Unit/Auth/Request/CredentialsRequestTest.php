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
    private const string PATH = '/login';
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';

    public function testReadsEmailAndPasswordFromForm(): void
    {
        $credentials = CredentialsRequest::fromRequest(self::request(['email' => self::EMAIL, 'password' => self::PASSWORD]));

        self::assertSame(self::EMAIL, $credentials->email);
        self::assertSame(self::PASSWORD, $credentials->password);
    }

    public function testRejectsFormWithoutBothFields(): void
    {
        $this->expectException(InvalidRequestException::class);

        CredentialsRequest::fromRequest(self::request(['email' => self::EMAIL]));
    }

    /** @param array<string, string> $form */
    private static function request(array $form): Request
    {
        return new Request(HttpMethodEnum::POST->value, self::PATH, [], $form);
    }
}
