<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Tests\Functional\Support\Browser;

final class SecurityHeadersTest extends TestCase
{
    private const string PANEL_PATH = '/login';
    private const string API_PATH = '/api/v1/products?limit=1';
    private const string GET_METHOD = 'GET';
    private const string CONTENT_SECURITY_POLICY = 'content-security-policy';
    private const string FRAME_ANCESTORS_NONE = "frame-ancestors 'none'";
    private const string SCRIPTS_FROM_OWN_ORIGIN = "script-src 'self'";

    /** @return array<string, array{string}> */
    public static function paths(): array
    {
        return ['panel page' => [self::PANEL_PATH], 'API response' => [self::API_PATH]];
    }

    #[DataProvider('paths')]
    public function testProtectsResponsesAndHidesSoftwareVersions(string $path): void
    {
        $headers = HttpClient::create()->request(self::GET_METHOD, Browser::url($path))->getHeaders();

        self::assertSame(['nosniff'], $headers['x-content-type-options'] ?? null);
        self::assertSame(['DENY'], $headers['x-frame-options'] ?? null);
        self::assertSame(['strict-origin-when-cross-origin'], $headers['referrer-policy'] ?? null);
        $policy = $headers[self::CONTENT_SECURITY_POLICY][0] ?? null;
        self::assertIsString($policy);
        self::assertStringContainsString(self::FRAME_ANCESTORS_NONE, $policy);
        self::assertStringContainsString(self::SCRIPTS_FROM_OWN_ORIGIN, $policy);
        self::assertArrayNotHasKey('server', $headers);
        self::assertArrayNotHasKey('x-powered-by', $headers);
    }
}
