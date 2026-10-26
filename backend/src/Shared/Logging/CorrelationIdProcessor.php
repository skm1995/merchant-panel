<?php

declare(strict_types=1);

namespace App\Shared\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final readonly class CorrelationIdProcessor implements ProcessorInterface
{
    private const string REQUEST_ID_KEY = 'request_id';

    public function __construct(private CorrelationId $correlation)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $id = $this->correlation->get();

        if ($id === null) {
            return $record;
        }

        return $record->with(extra: [...$record->extra, self::REQUEST_ID_KEY => $id]);
    }
}
