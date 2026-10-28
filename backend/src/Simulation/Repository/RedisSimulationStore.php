<?php

declare(strict_types=1);

namespace App\Simulation\Repository;

use App\Simulation\ReadModel\SimulationRun;
use Redis;

/**
 * Keeps simulations in Redis: a slot taken with SET NX and an expiry, so only one simulation
 * runs and the next waits for the cool-down; the current run as JSON; and counters that
 * concurrent buyers increment atomically.
 */
final readonly class RedisSimulationStore implements SimulationStoreInterface
{
    private const string SLOT_KEY = 'simulation:slot';
    private const string CURRENT_KEY = 'simulation:current';
    private const string COUNTERS_KEY_FORMAT = 'simulation:%s:counters';
    private const int COUNTERS_LIFETIME_SECONDS = 604800;

    public function __construct(private Redis $redis)
    {
    }

    public function reserveSlot(string $simulationId, int $seconds): bool
    {
        return $this->redis->set(self::SLOT_KEY, $simulationId, ['nx', 'ex' => $seconds]) === true;
    }

    public function secondsUntilFree(): int
    {
        return max(0, (int) $this->redis->ttl(self::SLOT_KEY));
    }

    public function save(SimulationRun $run): void
    {
        $this->redis->set(self::CURRENT_KEY, json_encode($run->toArray(), JSON_THROW_ON_ERROR));
    }

    public function findCurrent(): ?SimulationRun
    {
        $json = $this->redis->get(self::CURRENT_KEY);

        if (!is_string($json)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return SimulationRun::fromArray($data);
    }

    public function increment(string $simulationId, string $counter): void
    {
        $key = sprintf(self::COUNTERS_KEY_FORMAT, $simulationId);
        $this->redis->hIncrBy($key, $counter, 1);
        $this->redis->expire($key, self::COUNTERS_LIFETIME_SECONDS);
    }

    public function counts(string $simulationId): array
    {
        $counts = $this->redis->hGetAll(sprintf(self::COUNTERS_KEY_FORMAT, $simulationId));

        return is_array($counts) ? array_map(intval(...), $counts) : [];
    }
}
