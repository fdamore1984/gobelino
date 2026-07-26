package com.gobelino.agent.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import com.gobelino.agent.util.Prefs
import com.gobelino.agent.worker.PollWorker
import java.util.concurrent.TimeUnit

class BootCompletedReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED) return
        if (Prefs.of(context).deviceToken == null) return // not enrolled yet

        val interval = Prefs.of(context).pollIntervalSeconds.coerceAtLeast(60)
        val request = PeriodicWorkRequestBuilder<PollWorker>(interval.toLong(), TimeUnit.SECONDS).build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            "agent-poll",
            ExistingPeriodicWorkPolicy.KEEP,
            request
        )
    }
}
