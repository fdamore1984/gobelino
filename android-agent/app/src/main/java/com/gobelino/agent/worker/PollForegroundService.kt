package com.gobelino.agent.worker

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
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
 */
class PollForegroundService : Service() {

    private val serviceJob = Job()
    private val scope = CoroutineScope(Dispatchers.IO + serviceJob)

    override fun onCreate() {
        super.onCreate()
        startForeground(NOTIFICATION_ID, buildNotification())
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
            val nextDelaySeconds = PollRunner.runOnce(applicationContext)
            delay(nextDelaySeconds.coerceAtLeast(10) * 1000L)
        }
    }

    override fun onDestroy() {
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
                    lockscreenVisibility = Notification.VISIBILITY_SECRET
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
