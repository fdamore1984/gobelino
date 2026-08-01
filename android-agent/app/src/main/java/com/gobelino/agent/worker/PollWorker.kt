package com.gobelino.agent.worker

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.gobelino.agent.util.Prefs
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/**
 * Watchdog, not the primary poller: PollForegroundService now owns
 * the actual continuous check-in loop, since a running foreground
 * service survives Doze/App Standby in a way a WorkManager chain
 * alone doesn't. This worker just runs on a fixed, infrequent
 * schedule (see PollScheduler.WATCHDOG_INTERVAL_SECONDS) and checks
 * the heartbeat PollRunner writes on every cycle: if the service
 * hasn't reported in for too long, it's presumably been killed by
 * the OS/OEM, so this (a) restarts it, and (b) runs one check-in
 * itself via PollRunner so a command isn't stuck waiting for the
 * service restart to catch up.
 */
class PollWorker(appContext: Context, params: WorkerParameters) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val prefs = Prefs.of(applicationContext)
        val staleForMillis = System.currentTimeMillis() - prefs.lastHeartbeatAtMillis
        val staleThresholdMillis = (prefs.pollIntervalSeconds.coerceAtLeast(15) * 3L) * 1000L

        if (prefs.lastHeartbeatAtMillis == 0L || staleForMillis > staleThresholdMillis) {
            // No heartbeat yet, or too old: the foreground service is
            // presumably dead. Restart it and, meanwhile, don't leave a
            // pending command waiting — do one check-in ourselves.
            PollForegroundService.start(applicationContext)
            PollRunner.runOnce(applicationContext)
        }

        PollScheduler.scheduleWatchdog(applicationContext)
        Result.success()
    }
}
