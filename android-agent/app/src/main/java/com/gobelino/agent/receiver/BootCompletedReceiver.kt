package com.gobelino.agent.receiver

import android.app.AlarmManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.SystemClock
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
        scheduleRecoveryCheck(context)
    }

    /**
     * Rete di sicurezza indipendente da WorkManager: vedi kdoc di
     * BootRecoveryReceiver per il perche'. AlarmManager funziona
     * anche prima del primo sblocco, non serve un permesso a runtime
     * per un Device Owner (esentato dalle restrizioni sugli alarm
     * esatti).
     */
    private fun scheduleRecoveryCheck(context: Context) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
        val pendingIntent = PendingIntent.getBroadcast(
            context,
            0,
            Intent(context, BootRecoveryReceiver::class.java).apply {
                action = BootRecoveryReceiver.ACTION_BOOT_RECOVERY_CHECK
            },
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        alarmManager.setExactAndAllowWhileIdle(
            AlarmManager.ELAPSED_REALTIME_WAKEUP,
            SystemClock.elapsedRealtime() + RECOVERY_CHECK_DELAY_MILLIS,
            pendingIntent
        )
    }

    companion object {
        private const val RECOVERY_CHECK_DELAY_MILLIS = 45 * 1000L
    }
}
