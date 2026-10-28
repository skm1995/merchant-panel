<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Bus;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;

/**
 * Finds a command's or query's handler by name: the class in the same namespace whose name
 * ends in Handler instead of Command or Query. The handler is built by the container only
 * when its message is sent, so no handler is created or discovered in advance.
 */
final readonly class ConventionHandlersLocator implements HandlersLocatorInterface
{
    private const string MESSAGE_SUFFIX_PATTERN = '/(Command|Query)$/';
    private const string HANDLER_SUFFIX = 'Handler';
    private const string HANDLE_METHOD = 'handle';

    public function __construct(private ContainerInterface $services)
    {
    }

    public function getHandlers(Envelope $envelope): iterable
    {
        $messageClass = $envelope->getMessage()::class;
        $handlerClass = (string) preg_replace(self::MESSAGE_SUFFIX_PATTERN, self::HANDLER_SUFFIX, $messageClass);

        if ($handlerClass === $messageClass || !method_exists($handlerClass, self::HANDLE_METHOD)) {
            throw new MissingHandlerException($messageClass, $handlerClass);
        }

        $services = $this->services;

        yield new HandlerDescriptor(static function (object $message) use ($services, $handlerClass): mixed {
            $handler = $services->get($handlerClass);

            return $handler->{self::HANDLE_METHOD}($message);
        });
    }
}
