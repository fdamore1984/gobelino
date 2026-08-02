package com.gobelino.agent.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import com.gobelino.agent.util.Prefs
import com.gobelino.agent.worker.PollScheduler

class BootCompletedReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        // LOCKED_BOOT_COMPLETED fires immediately at boot, before the
        // first unlock — that's the one that matters. BOOT_COMPLETED
        // fires again later (only once the user unlocks); we still
        // handle it too as a harmless second call: scheduleNow() is
        // idempotent (KEEP policy on the watchdog, cheap no-op if the
        // service is already running).
        if (intent.action != Intent.ACTION_BOOT_COMPLETED &&
            intent.action != "android.intent.action.LOCKED_BOOT_COMPLETED"
        ) return
        if (Prefs.of(context).deviceToken == null) return // not enrolled yet

        // KEEP: don't disrupt a check-in that's already scheduled/pending.
        PollScheduler.scheduleNow(context)
    }
}
