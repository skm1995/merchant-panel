<?php

declare(strict_types=1);

namespace Tests\Functional\Messaging;

use PHPUnit\Framework\TestCase;

/**
 * The worker as Docker runs it. It keeps the database connection of its first message, and
 * MySQL closes a connection left idle for hours, which no later message in that process
 * survives, so the worker must end in time for Docker to start a fresh one.
 */
final class WorkerProcessTest extends TestCase
{
    private const string TIME_LIMIT_VARIABLE = 'WORKER_TIME_LIMIT_SECONDS';
    private const string TIME_LIMIT_SECONDS = '1';
    private const int WAIT_TIMEOUT_SECONDS = 20;
    private const int POLL_INTERVAL_MICROSECONDS = 100000;
    private const string COMMAND_PHP = 'php';
    private const string COMMAND_SCRIPT = 'bin/worker.php';
    private const string DISCARD = '/dev/null';
    private const string WRITE = 'w';
    private const string FILE_DESCRIPTOR = 'file';
    private const string RUNNING_KEY = 'running';
    private const string EXIT_CODE_KEY = 'exitcode';

    public function testTheWorkerEndsCleanlyOnceItsTimeLimitPasses(): void
    {
        $output = [self::FILE_DESCRIPTOR, self::DISCARD, self::WRITE];
        $process = proc_open(
            [self::COMMAND_PHP, self::COMMAND_SCRIPT],
            [1 => $output, 2 => $output],
            $pipes,
            dirname(__DIR__, 3),
            [...getenv(), self::TIME_LIMIT_VARIABLE => self::TIME_LIMIT_SECONDS],
        );
        self::assertIsResource($process);
        $deadline = microtime(true) + self::WAIT_TIMEOUT_SECONDS;

        do {
            $status = proc_get_status($process);
            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while ($status[self::RUNNING_KEY] && microtime(true) < $deadline);

        proc_terminate($process);
        proc_close($process);

        self::assertFalse($status[self::RUNNING_KEY], 'The worker kept running past its time limit.');
        self::assertSame(0, $status[self::EXIT_CODE_KEY]);
    }
}
