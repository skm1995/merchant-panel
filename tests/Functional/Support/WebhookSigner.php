<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Shared\Config\Environment;

/**
 * Signs webhook bodies the way the simulated payment providers do.
 */
final class WebhookSigner
{
    private const string FAKEPAY_SECRET = 'FAKEPAY_WEBHOOK_SECRET';
    private const string QUICKPAY_SECRET = 'QUICKPAY_WEBHOOK_SECRET';
    private const string FAKEPAY_HEADER = 'X-Fakepay-Signature';
    private const string QUICKPAY_HEADER = 'X-Quickpay-Signature';
    private const string QUICKPAY_SIGNATURE_FORMAT = 't=%d,v1=%s';
    private const string QUICKPAY_SIGNED_CONTENT_FORMAT = '%d.%s';
    private const string HASH_ALGORITHM = 'sha256';

    /** @return array<string, string> */
    public static function fakepay(string $payload): array
    {
        return [self::FAKEPAY_HEADER => hash_hmac(self::HASH_ALGORITHM, $payload, Environment::get(self::FAKEPAY_SECRET))];
    }

    /** @return array<string, string> */
    public static function quickpay(string $payload, ?int $timestamp = null): array
    {
        $timestamp ??= time();
        $signature = hash_hmac(
            self::HASH_ALGORITHM,
            sprintf(self::QUICKPAY_SIGNED_CONTENT_FORMAT, $timestamp, $payload),
            Environment::get(self::QUICKPAY_SECRET),
        );

        return [self::QUICKPAY_HEADER => sprintf(self::QUICKPAY_SIGNATURE_FORMAT, $timestamp, $signature)];
    }
}
