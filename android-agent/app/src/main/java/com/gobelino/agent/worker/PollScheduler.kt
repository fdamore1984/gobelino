package com.gobelino.agent.worker

import android.content.Context
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import java.util.UUID
import java.util.concurrent.TimeUnit

/**
 * PollForegroundService (see that file) is now the primary poller.
 * This object's job shrunk to: (1) make sure the foreground service
 * is started, and (2) run a periodic watchdog that restarts it if
 * it's been killed. PeriodicWorkRequest enforces a system-wide
 * minimum interval of 15 minutes, which is fine for a watchdog check
 * (unlike for the actual ~15s check-ins, which is why polling itself
 * lives in the foreground service instead). The watchdog still uses
 * a self-chaining OneTimeWorkRequest rather than PeriodicWorkRequest
 * so the interval stays easy to read/tune in one place.
 */
object PollScheduler {
    private const val WORK_NAME = "agent-poll-watchdog"
    private const val WATCHDOG_INTERVAL_SECONDS = 5 * 60L

    /** Schedules the next watchdog check `WATCHDOG_INTERVAL_SECONDS` from now. */
    fun scheduleWatchdog(context: Context) {
        val request = OneTimeWorkRequestBuilder<PollWorker>()
            .setInitialDelay(WATCHDOG_INTERVAL_SECONDS, TimeUnit.SECONDS)
            .build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            WORK_NAME,
            ExistingWorkPolicy.REPLACE,
            request
        )
    }

    /**
     * Kicks everything off (device just provisioned/booted): starts
     * the foreground service and arms the watchdog. Uses KEEP for the
     * watchdog so it doesn't reset a check already pending.
     */
    fun scheduleNow(context: Context) {
        PollForegroundService.start(context)

        val request = OneTimeWorkRequestBuilder<PollWorker>().build()
        WorkManager.getInstance(context).enqueueUniqueWork(
            WORK_NAME,
            ExistingWorkPolicy.KEEP,
            request
        )
    }

    /**
     * Manual "forza connessione" button in MainActivity: makes sure
     * the foreground service is running (cheap no-op if it already
     * is) and runs one check-in immediately via PollRunner so the
     * result shows up without waiting for the service's own loop to
     * come back around.
     *
     * NOTE: deliberately not using WorkManager's setExpedited() here.
     * On API 31+, truly-expedited CoroutineWorkers must override
     * getForegroundInfo() to supply a notification, or the base class
     * throws IllegalStateException("Not implemented") the moment the
     * system tries to promote the work — that's what used to crash
     * the agent every time this button was pressed, before the poll
     * loop moved into a real foreground service.
     */
    suspend fun forceNow(context: Context) {
        PollForegroundService.start(context)
        PollRunner.runOnce(context)
    }
}
