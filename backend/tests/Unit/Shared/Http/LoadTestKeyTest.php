<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\LoadTestKey;
use App\Shared\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoadTestKeyTest extends TestCase
{
    private const string KEY = 'test-load-test-key';

    #[DataProvider('internalAddresses')]
    public function testIdentifiesTheRightKeyFromInsideTheServer(string $address): void
    {
        self::assertTrue(new LoadTestKey(self::KEY)->identifiesSimulator(self::request(self::KEY, $address)));
    }

    /** @return iterable<string, array{string}> */
    public static function internalAddresses(): iterable
    {
        yield 'Docker network' => ['172.18.0.7'];
        yield 'private class A' => ['10.0.0.12'];
        yield 'private class C' => ['192.168.1.20'];
        yield 'loopback' => ['127.0.0.1'];
        yield 'IPv6 loopback' => ['::1'];
        yield 'IPv6 unique local' => ['fd00::12'];
    }

    #[DataProvider('refusals')]
    public function testRefusesAWrongKeyOrAnAddressFromTheInternet(string $key, ?string $address): void
    {
        self::assertFalse(new LoadTestKey(self::KEY)->identifiesSimulator(self::request($key, $address)));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function refusals(): iterable
    {
        yield 'right key from the internet' => [self::KEY, '8.8.8.8'];
        yield 'right key from a public IPv6 address' => [self::KEY, '2001:4860:4860::8888'];
        yield 'right key without an address' => [self::KEY, null];
        yield 'wrong key from inside' => ['guessed', '172.18.0.7'];
        yield 'empty key from inside' => ['', '172.18.0.7'];
    }

    public function testAnEmptyConfiguredKeyIdentifiesNobody(): void
    {
        self::assertFalse(new LoadTestKey('')->identifiesSimulator(self::request('', '172.18.0.7')));
    }

    private static function request(string $key, ?string $address): Request
    {
        return new Request(HttpMethodEnum::POST->value, '/api/v1/carts', headers: ['x-load-test-key' => $key], clientAddress: $address);
    }
}
