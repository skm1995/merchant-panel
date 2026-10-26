<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    private const string KEY = 'session_test_key';
    private const string VALUE = 'value';

    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session();
        $this->session->start();
    }

    protected function tearDown(): void
    {
        unset($_SESSION[self::KEY]);
    }

    public function testStoresReadsAndRemovesValues(): void
    {
        self::assertNull($this->session->get(self::KEY));

        $this->session->set(self::KEY, self::VALUE);
        self::assertSame(self::VALUE, $this->session->get(self::KEY));

        $this->session->remove(self::KEY);
        self::assertNull($this->session->get(self::KEY));
    }

    public function testKeepsValuesWhenTheIdIsRegenerated(): void
    {
        $this->session->set(self::KEY, self::VALUE);

        $this->session->regenerateId();

        self::assertSame(self::VALUE, $this->session->get(self::KEY));
    }
}
