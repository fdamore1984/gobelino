package com.gobelino.agent.worker

import android.content.Context
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import java.util.UUID
import java.util.concurrent.TimeUnit

/**
 * PeriodicWorkRequest enforces a system-wide minimum interval of 15
 * minutes no matter what you pass to the builder, which makes it
 * useless for the ~60s check-ins we want. The standard workaround is
 * a self-rescheduling chain of OneTimeWorkRequests: PollWorker calls
 * scheduleNext() itself when it's done, with whatever interval the
 * backend last returned. No special permission is needed (unlike
 * exact AlarmManager alarms).
 */
object PollScheduler {
    private const val WORK_NAME = "agent-poll"

    /** Schedules the next check-in `delaySeconds` from now (min. 10s — the
     *  15s "just executed a command, report back fast" follow-up needs to
     *  fit under this, unlike the normal ~60s interval). */
    fun scheduleNext(context: Context, delaySeconds: Int) {
        val request = OneTimeWorkRequestBuilder<PollWorker>()
            .setInitialDelay(delaySeconds.coerceAtLeast(10).toLong(), TimeUnit.SECONDS)
            .build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            WORK_NAME,
            ExistingWorkPolicy.REPLACE,
            request
        )
    }

    /**
     * Kicks off the chain immediately (device just provisioned/booted).
     * Uses KEEP so it doesn't cancel a check-in already pending.
     */
    fun scheduleNow(context: Context) {
        val request = OneTimeWorkRequestBuilder<PollWorker>().build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            WORK_NAME,
            ExistingWorkPolicy.KEEP,
            request
        )
    }

    /**
     * Manual "forza connessione" button in MainActivity: unlike
     * scheduleNow(), this uses REPLACE so it cancels any pending wait
     * and runs right away.
     *
     * NOTE: deliberately NOT using setExpedited() here. On API 31+,
     * truly-expedited CoroutineWorkers must override getForegroundInfo()
     * to supply a notification, or the base class throws
     * IllegalStateException("Not implemented") the moment the system
     * tries to promote the work — that's exactly what was crashing the
     * agent every time this button was pressed. Since the button is
     * only pressed while the app is already in the foreground, a plain
     * immediate OneTimeWorkRequest already runs promptly without
     * needing expedited priority (or a permanent notification, which
     * would be undesirable for a background MDM agent anyway).
     * Returns the request id so the caller can observe completion.
     */
    fun forceNow(context: Context): UUID {
        val request = OneTimeWorkRequestBuilder<PollWorker>().build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            WORK_NAME,
            ExistingWorkPolicy.REPLACE,
            request
        )

        return request.id
    }
}
