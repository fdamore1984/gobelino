<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Lets DeviceAgentController::poll() block (long-poll) until a new
 * command is queued for the device, instead of returning immediately
 * and forcing the agent to wait out a full poll_interval_seconds
 * before asking again. DeviceCommandController::store() calls
 * notify() right after queuing a command; poll() calls wait() when
 * it finds nothing pending yet.
 *
 * Backed by a per-device Redis list: notify() RPUSHes a throwaway
 * value, wait() BLPOPs with a timeout. Whichever happens first wins,
 * same as any producer/consumer queue — no pub/sub "must already be
 * listening" race to worry about.
 *
 * Fails safe in both directions: any Redis error is swallowed. A
 * command must always get queued even if Redis is down (falls back
 * to the old "wait out the interval" behavior for that one request),
 * and a poll() must never 500 just because it couldn't block.
 */
class DeviceWakeupService
{
    /** Cap on how long a single poll() blocks before returning empty. */
    public const LONG_POLL_TIMEOUT_SECONDS = 25;

    /** Attesa usata come fallback quando Redis non e' raggiungibile. */
    private const FALLBACK_WAIT_SECONDS = 10;

    /** Wakes up any poll() currently blocked waiting for this device. */
    public function notify(int $deviceId): void
    {
        try {
            $key = $this->key($deviceId);
            Redis::connection()->rpush($key, '1');
            // Cap growth for a device that stays offline while several
            // commands get queued for it in the meantime — only the
            // next wait() needs to wake up once, not once per command.
            Redis::connection()->ltrim($key, 0, 9);
            Redis::connection()->expire($key, 3600);
        } catch (Throwable $e) {
            // Swallowed on purpose, see class docblock.
        }
    }

    /** Blocks up to $timeoutSeconds waiting for a notify() call for this device. */
    public function wait(int $deviceId, int $timeoutSeconds = self::LONG_POLL_TIMEOUT_SECONDS): void
    {
        try {
            Redis::connection()->blpop([$this->key($deviceId)], $timeoutSeconds);
        } catch (Throwable $e) {
            // Redis non raggiungibile: niente vero long-poll, ma non
            // possiamo tornare istantaneamente o l'agent (che ora non
            // aggiunge piu' un'attesa lato client dopo un round-trip
            // riuscito) martellerebbe il server in loop stretto.
            // Fallback: aspetta comunque un intervallo minimo.
            sleep(min($timeoutSeconds, self::FALLBACK_WAIT_SECONDS));
        }
    }

    private function key(int $deviceId): string
    {
        return "device:{$deviceId}:wakeup";
    }
}
