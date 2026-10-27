<?php

declare(strict_types=1);

namespace Tests\Functional\Http;

use App\Shared\Config\Environment;
use App\Shared\Http\Enum\HttpMethodEnum;
use PHPUnit\Framework\TestCase;
use Redis;
use Tests\Functional\Support\Browser;

final class SessionStorageTest extends TestCase
{
    private const string SESSION_PATH = '/api/v1/session';
    private const string SESSION_COOKIE = 'PHPSESSID';
    private const string SESSION_KEY_FORMAT = 'PHPREDIS_SESSION:%s';
    private const string REDIS_HOST = 'REDIS_HOST';
    private const string REDIS_PORT = 'REDIS_PORT';
    private const string REDIS_SESSION_DATABASE = 'REDIS_SESSION_DATABASE';

    public function testKeepsSessionsInRedisSoReplacingTheWebContainerKeepsMerchantsSignedIn(): void
    {
        $browser = Browser::create();
        $browser->request(HttpMethodEnum::GET->value, Browser::url(self::SESSION_PATH));
        $sessionId = $browser->getCookieJar()->get(self::SESSION_COOKIE)?->getValue();
        self::assertIsString($sessionId);

        $redis = new Redis();
        $redis->connect(Environment::get(self::REDIS_HOST), Environment::positiveInteger(self::REDIS_PORT));
        $redis->select(Environment::positiveInteger(self::REDIS_SESSION_DATABASE));

        self::assertSame(1, $redis->exists(sprintf(self::SESSION_KEY_FORMAT, $sessionId)));
    }
}
