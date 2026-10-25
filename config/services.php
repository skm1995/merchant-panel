<?php

declare(strict_types=1);

use App\Auth\Repository\EmailVerificationTokenRepositoryInterface;
use App\Auth\Repository\PdoEmailVerificationTokenRepository;
use App\Auth\Repository\PdoUserRepository;
use App\Auth\Repository\UserRepositoryInterface;
use App\Cart\Repository\CartRepositoryInterface;
use App\Cart\Repository\PdoCartRepository;
use App\Catalog\Infrastructure\Cache\CachedProductRepository;
use App\Catalog\Repository\ProductRepositoryInterface;
use App\Order\Repository\OrderReadRepositoryInterface;
use App\Order\Repository\OrderRepositoryInterface;
use App\Order\Repository\PdoOrderReadRepository;
use App\Order\Repository\PdoOrderRepository;
use App\Payment\Gateway\PaymentGatewayFactoryInterface;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use App\Payment\Repository\PaymentReadRepositoryInterface;
use App\Payment\Repository\PaymentRepositoryInterface;
use App\Payment\Repository\PdoPaymentReadRepository;
use App\Payment\Repository\PdoPaymentRepository;
use App\Payment\Repository\PdoWebhookInboxRepository;
use App\Payment\Repository\WebhookInboxRepositoryInterface;
use App\Report\Repository\PdoReportReadRepository;
use App\Report\Repository\ReportReadRepositoryInterface;
use App\Shared\Cache\CacheStoreInterface;
use App\Shared\Cache\RedisCacheStore;
use App\Shared\Cache\RedisConnection;
use App\Shared\Config\Environment;
use App\Shared\Database\DatabaseConnection;
use App\Shared\Database\PdoTransactionRunner;
use App\Shared\Database\TransactionRunnerInterface;
use App\Shared\DependencyInjection\Container;
use App\Shared\Messaging\MessengerRuntime;
use App\Shared\Messaging\Outbox\OutboxInterface;
use App\Shared\Messaging\Outbox\OutboxMessageTypes;
use App\Shared\Messaging\Outbox\OutboxStoreInterface;
use App\Shared\Messaging\Outbox\PdoOutbox;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Messenger\MessageBusInterface;

/*
 * Service definitions for the autowiring container. Concrete classes need no entry: they are
 * built from their constructor types. List only interface bindings and services that need
 * custom construction.
 */
return [
    // Interfaces bound to their implementations.
    ClockInterface::class => NativeClock::class,
    TransactionRunnerInterface::class => PdoTransactionRunner::class,
    OutboxInterface::class => PdoOutbox::class,
    OutboxStoreInterface::class => PdoOutbox::class,
    CacheStoreInterface::class => RedisCacheStore::class,
    UserRepositoryInterface::class => PdoUserRepository::class,
    EmailVerificationTokenRepositoryInterface::class => PdoEmailVerificationTokenRepository::class,
    ProductRepositoryInterface::class => CachedProductRepository::class,
    CartRepositoryInterface::class => PdoCartRepository::class,
    OrderRepositoryInterface::class => PdoOrderRepository::class,
    OrderReadRepositoryInterface::class => PdoOrderReadRepository::class,
    PaymentRepositoryInterface::class => PdoPaymentRepository::class,
    PaymentReadRepositoryInterface::class => PdoPaymentReadRepository::class,
    WebhookInboxRepositoryInterface::class => PdoWebhookInboxRepository::class,
    PaymentGatewayFactoryInterface::class => PaymentGatewayFactory::class,
    ReportReadRepositoryInterface::class => PdoReportReadRepository::class,

    // Services with custom construction.
    PDO::class => static fn (): PDO => DatabaseConnection::createFromEnvironment(),
    Redis::class => static fn (): Redis => RedisConnection::createFromEnvironment(),
    MailerInterface::class => static fn (): MailerInterface => new Mailer(Transport::fromDsn(Environment::get('MAILER_DSN'))),
    MessengerRuntime::class => static fn (): MessengerRuntime => MessengerRuntime::fromConfiguration(),
    MessageBusInterface::class => static fn (Container $container): MessageBusInterface => $container->get(MessengerRuntime::class)->bus(),
    OutboxMessageTypes::class => static fn (Container $container): OutboxMessageTypes => $container->get(MessengerRuntime::class)->outboxMessageTypes(),
];
