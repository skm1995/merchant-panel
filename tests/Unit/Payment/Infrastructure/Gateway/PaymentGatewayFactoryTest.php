<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Infrastructure\Gateway;

use App\Payment\Enum\PaymentProviderEnum;
use App\Payment\Infrastructure\Gateway\FakePayGateway;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use App\Payment\Infrastructure\Gateway\QuickPayGateway;
use App\Shared\Config\Exception\MissingEnvironmentVariableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tests\Unit\Payment\PaymentFixtures;

final class PaymentGatewayFactoryTest extends TestCase
{
    private const array VARIABLES = [
        'FAKEPAY_CHECKOUT_URL',
        'FAKEPAY_WEBHOOK_SECRET',
        'QUICKPAY_CHECKOUT_URL',
        'QUICKPAY_WEBHOOK_SECRET',
    ];
    private const string ASSIGNMENT_FORMAT = '%s=%s';

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $variable) {
            $this->originalEnvironment[$variable] = getenv($variable);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $variable => $value) {
            putenv($value === false ? $variable : sprintf(self::ASSIGNMENT_FORMAT, $variable, $value));
        }
    }

    public function testCreatesTheGatewayForEachProvider(): void
    {
        foreach (self::VARIABLES as $variable) {
            putenv(sprintf(self::ASSIGNMENT_FORMAT, $variable, PaymentFixtures::CHECKOUT_BASE_URL));
        }

        $factory = new PaymentGatewayFactory(new MockClock());

        self::assertInstanceOf(FakePayGateway::class, $factory->create(PaymentProviderEnum::FAKEPAY));
        self::assertInstanceOf(QuickPayGateway::class, $factory->create(PaymentProviderEnum::QUICKPAY));
    }

    public function testRequiresConfigurationOnlyForTheRequestedProvider(): void
    {
        putenv(sprintf(self::ASSIGNMENT_FORMAT, 'FAKEPAY_CHECKOUT_URL', PaymentFixtures::CHECKOUT_BASE_URL));
        putenv(sprintf(self::ASSIGNMENT_FORMAT, 'FAKEPAY_WEBHOOK_SECRET', PaymentFixtures::WEBHOOK_SECRET));
        putenv('QUICKPAY_WEBHOOK_SECRET');
        $factory = new PaymentGatewayFactory(new MockClock());

        self::assertInstanceOf(FakePayGateway::class, $factory->create(PaymentProviderEnum::FAKEPAY));
        $this->expectException(MissingEnvironmentVariableException::class);
        $factory->create(PaymentProviderEnum::QUICKPAY);
    }
}
