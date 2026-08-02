package com.gobelino.agent.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import com.gobelino.agent.util.Prefs
import com.gobelino.agent.worker.PollForegroundService

/**
 * Controllo di sicurezza a breve termine dopo un riavvio, in aggiunta
 * al normale watchdog di PollWorker (ogni 5 minuti).
 *
 * Perche' non basta PollScheduler.scheduleNow() da solo: quel metodo
 * prova gia' ad avviare subito PollForegroundService da
 * BootCompletedReceiver, ma se quel primo tentativo fallisce in
 * silenzio (alcuni OEM limitano l'avvio di foreground service subito
 * dopo il boot), il prossimo tentativo affidabile sarebbe il
 * watchdog WorkManager — che pero' puo' arrivare fino a 5 minuti
 * dopo, e il cui stesso storage potrebbe non essere ancora
 * disponibile appena dopo il boot (vedi kdoc di scheduleNow()).
 *
 * Questo receiver viene invece armato via AlarmManager direttamente
 * da BootCompletedReceiver: non dipende dal database di WorkManager,
 * quindi funziona in modo affidabile anche prima del primo sblocco.
 * Circa 45 secondi dopo il boot, controlla l'heartbeat e riavvia il
 * servizio se non e' partito — riducendo la finestra di
 * irraggiungibilita' da "fino a 5 minuti" a "circa 45 secondi".
 */
class BootRecoveryReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != ACTION_BOOT_RECOVERY_CHECK) return

        val prefs = Prefs.of(context)
        if (prefs.deviceToken == null) return // not enrolled yet

        val staleForMillis = System.currentTimeMillis() - prefs.lastHeartbeatAtMillis
        val staleThresholdMillis = (prefs.pollIntervalSeconds.coerceAtLeast(15) * 3L) * 1000L

        if (prefs.lastHeartbeatAtMillis == 0L || staleForMillis > staleThresholdMillis) {
            // Nessun heartbeat recente: il tentativo di avvio da
            // BootCompletedReceiver e' presumibilmente fallito.
            PollForegroundService.start(context)
        }
    }

    companion object {
        const val ACTION_BOOT_RECOVERY_CHECK = "com.gobelino.agent.action.BOOT_RECOVERY_CHECK"
    }
}
