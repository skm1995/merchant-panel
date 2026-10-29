<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Request;

use App\Auth\Request\ResetLinkRequest;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;

final class ResetLinkRequestTest extends TestCase
{
    private const string API_PATH = '/api/v1/password-resets';

    public function testReadsTheEmailFromJson(): void
    {
        $request = new Request(HttpMethodEnum::POST->value, self::API_PATH, body: '{"email":"person@example.com"}');

        self::assertSame('person@example.com', ResetLinkRequest::fromJson($request)->email);
    }

    public function testRejectsJsonWithoutAnEmail(): void
    {
        $this->expectException(InvalidRequestException::class);

        ResetLinkRequest::fromJson(new Request(HttpMethodEnum::POST->value, self::API_PATH, body: '{"email":null}'));
    }
}
