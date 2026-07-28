package com.gobelino.agent.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import com.gobelino.agent.util.Prefs
import com.gobelino.agent.worker.PollScheduler

class BootCompletedReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED) return
        if (Prefs.of(context).deviceToken == null) return // not enrolled yet

        // KEEP: don't disrupt a check-in that's already scheduled/pending.
        PollScheduler.scheduleNow(context)
    }
}
