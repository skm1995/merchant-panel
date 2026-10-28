<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Messaging\Bus;

use App\Shared\Messaging\Bus\MessengerCommandBus;
use App\Shared\Messaging\Bus\MessengerQueryBus;
use App\Shared\Messaging\Bus\MissingHandlerException;
use App\Shared\Messaging\RuntimeServiceContainer;
use DomainException;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Shared\Messaging\Fixture\Bus\CountItemsHandler;
use Tests\Unit\Shared\Messaging\Fixture\Bus\CountItemsQuery;
use Tests\Unit\Shared\Messaging\Fixture\Bus\OrphanCommand;
use Tests\Unit\Shared\Messaging\Fixture\Bus\RenameItemCommand;
use Tests\Unit\Shared\Messaging\Fixture\Bus\RenameItemHandler;

final class MessengerBusTest extends TestCase
{
    private TestHandler $log;

    protected function setUp(): void
    {
        $this->log = new TestHandler();
    }

    public function testACommandReachesTheHandlerNamedAfterItAndReturnsItsResult(): void
    {
        self::assertSame('renamed to mug', $this->commands()->dispatch(new RenameItemCommand('mug')));
    }

    public function testLogsEveryHandledCommandWithItsDuration(): void
    {
        $this->commands()->dispatch(new RenameItemCommand('mug'));

        $record = $this->log->getRecords()[0] ?? null;
        self::assertNotNull($record);
        self::assertSame('Command handled', $record->message);
        self::assertSame(Level::Info, $record->level);
        self::assertSame(RenameItemCommand::class, $record->context['command']);
        self::assertIsInt($record->context['duration_ms']);
    }

    public function testRethrowsTheHandlersOwnExceptionAndLogsTheFailure(): void
    {
        try {
            $this->commands()->dispatch(new RenameItemCommand(RenameItemHandler::REFUSED_NAME));
            self::fail('The handler refuses the name.');
        } catch (DomainException $exception) {
            self::assertSame('This name is refused.', $exception->getMessage(), 'Controllers map domain exceptions to HTTP statuses, so the bus must not wrap them.');
        }

        $record = $this->log->getRecords()[0] ?? null;
        self::assertNotNull($record);
        self::assertSame('Command failed', $record->message);
        self::assertSame(Level::Warning, $record->level);
    }

    public function testNamesTheHandlerAMessageIsMissing(): void
    {
        $this->expectException(MissingHandlerException::class);
        $this->expectExceptionMessage('OrphanHandler');

        $this->commands()->dispatch(new OrphanCommand());
    }

    public function testAQueryReturnsItsHandlersResultWithoutBeingLogged(): void
    {
        self::assertSame(3, new MessengerQueryBus($this->handlers())->ask(new CountItemsQuery()));
        self::assertSame([], $this->log->getRecords());
    }

    private function commands(): MessengerCommandBus
    {
        return new MessengerCommandBus($this->handlers(), new Logger('test', [$this->log]));
    }

    /**
     * Handlers are services of the container, built only when a message needs them.
     */
    private function handlers(): RuntimeServiceContainer
    {
        return new RuntimeServiceContainer([
            RenameItemHandler::class => new RenameItemHandler(),
            CountItemsHandler::class => new CountItemsHandler(),
        ]);
    }
}
