package com.gobelino.agent.worker

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import android.os.PowerManager
import androidx.core.app.NotificationCompat
import com.gobelino.agent.R
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

/**
 * Foreground service alternative to the WorkManager chain: while a
 * foreground service is actively running, Android does not throttle
 * it under Doze/App Standby the way it does OneTimeWorkRequest chains
 * — this is what keeps command latency low even with the screen off
 * for a long time. The WorkManager chain (PollWorker/PollScheduler)
 * is kept as a watchdog: if this service gets killed anyway (some
 * OEM battery managers ignore the foreground-service exemption), the
 * watchdog notices via the heartbeat and restarts it.
 *
 * The notification is deliberately minimal/low-importance: it exists
 * because Android requires *some* visible notification for a running
 * foreground service, not to inform the user of anything actionable.
 * setOngoing(true) prevents swipe-to-dismiss (the only reliable,
 * public way to make a notification "not removable" — Android has no
 * API to block the user from disabling it entirely via Settings or
 * force-stopping the app; this gets us as close as the platform allows).
 *
 * Being a foreground service exempts the *process* from Doze/App
 * Standby throttling, but it does NOT by itself keep the CPU out of
 * deep sleep once the screen has been off long enough — the
 * pollLoop()'s delay() is a plain timer, and a sleeping CPU simply
 * doesn't run it until something else wakes the device up first.
 * That's what caused commands to stop arriving after long screen-off
 * periods. A PARTIAL_WAKE_LOCK, held for the loop's lifetime and
 * renewed on every cycle (bounded by WAKE_LOCK_TIMEOUT_MILLIS so a
 * crash before onDestroy() can't leak it), keeps the CPU awake for
 * this specifically.
 */
class PollForegroundService : Service() {

    private val serviceJob = Job()
    private val scope = CoroutineScope(Dispatchers.IO + serviceJob)
    private var wakeLock: PowerManager.WakeLock? = null

    override fun onCreate() {
        super.onCreate()
        startForeground(NOTIFICATION_ID, buildNotification())
        val pm = getSystemService(Context.POWER_SERVICE) as PowerManager
        wakeLock = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "gobelino:poll").apply {
            setReferenceCounted(false)
        }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        // enqueueUniqueWork-style idempotency: if the loop is already
        // running (e.g. system redelivered onStartCommand), don't spawn
        // a second one.
        if (serviceJob.children.none { it.isActive }) {
            scope.launch { pollLoop() }
        }
        return START_STICKY
    }

    private suspend fun pollLoop() {
        while (scope.isActive) {
            // No floor here on purpose: with /poll now long-polling
            // server-side, a successful cycle returns MIN_LOOP_DELAY_SECONDS
            // (currently 1s) because the "waiting" already happened
            // inside the HTTP call itself — a floor like the old 10s
            // would silently cancel out that latency improvement.
            // runOnce() already falls back to poll_interval_seconds on
            // any failure, which is what actually prevents tight-looping
            // against a dead network/server.
            val waitSeconds = PollRunner.runOnce(applicationContext)
            // Renew every cycle rather than acquire-once-forever: if the
            // service ever dies without onDestroy() running (OEM kill,
            // crash), the lock still expires on its own instead of
            // draining the battery indefinitely.
            wakeLock?.acquire(WAKE_LOCK_TIMEOUT_MILLIS.coerceAtLeast((waitSeconds + 60) * 1000L))
            delay(waitSeconds * 1000L)
        }
    }

    override fun onDestroy() {
        wakeLock?.let { if (it.isHeld) it.release() }
        serviceJob.cancel()
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun buildNotification(): Notification {
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val existing = manager.getNotificationChannel(CHANNEL_ID)
            if (existing == null) {
                val channel = NotificationChannel(
                    CHANNEL_ID,
                    getString(R.string.agent_service_channel_name),
                    NotificationManager.IMPORTANCE_MIN
                ).apply {
                    setShowBadge(false)
                    // PUBLIC (non SECRET): deve comparire anche a schermo
                    // bloccato subito dopo un riavvio, cosi' si vede a
                    // colpo d'occhio se il servizio e' effettivamente
                    // ripartito senza dover sbloccare il dispositivo.
                    lockscreenVisibility = Notification.VISIBILITY_PUBLIC
                }
                manager.createNotificationChannel(channel)
            }
        }

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(getString(R.string.agent_service_notification_title))
            .setSmallIcon(R.drawable.ic_launcher)
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .setOngoing(true)
            .setSilent(true)
            .setShowWhen(false)
            .build()
    }

    companion object {
        private const val CHANNEL_ID = "gobelino_agent_service"
        private const val NOTIFICATION_ID = 1
        // Floor for the wake lock timeout: always at least this long,
        // and extended further below when a single cycle's own wait is
        // longer than this (so a slow poll interval never outlives the
        // lock that's supposed to keep the CPU awake for it).
        private const val WAKE_LOCK_TIMEOUT_MILLIS = 10 * 60 * 1000L

        fun start(context: Context) {
            val intent = Intent(context, PollForegroundService::class.java)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent)
            } else {
                context.startService(intent)
            }
        }
    }
}
