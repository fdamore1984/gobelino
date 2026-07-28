package com.gobelino.agent.worker

import android.content.Context
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
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
}
