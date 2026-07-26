package com.gobelino.agent

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.os.Bundle
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import com.gobelino.agent.receiver.AgentDeviceAdminReceiver
import com.gobelino.agent.util.Prefs
import com.gobelino.agent.worker.PollWorker
import java.util.concurrent.TimeUnit

/**
 * Launcher activity. In normal operation the device just sits on
 * this screen (or, in kiosk mode, is pinned to it via LockTask) while
 * PollWorker does all the real work in the background.
 */
class MainActivity : AppCompatActivity() {

    private lateinit var statusText: TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        statusText = findViewById(R.id.statusText)

        ensurePollingScheduled()
        updateStatus()
    }

    override fun onResume() {
        super.onResume()
        applyKioskState()
        updateStatus()
    }

    private fun ensurePollingScheduled() {
        val prefs = Prefs.of(this)
        if (prefs.serverUrl == null) return // not provisioned yet

        val interval = prefs.pollIntervalSeconds.coerceAtLeast(60)
        val request = PeriodicWorkRequestBuilder<PollWorker>(interval.toLong(), TimeUnit.SECONDS).build()

        WorkManager.getInstance(this).enqueueUniquePeriodicWork(
            "agent-poll",
            ExistingPeriodicWorkPolicy.KEEP,
            request
        )
    }

    /**
     * Kiosk mode uses Android's LockTask API: only possible because
     * this app is Device Owner and was whitelisted via
     * setLockTaskPackages() in AgentDeviceAdminReceiver. It can only
     * be started/stopped from a foreground activity, hence checking
     * the flag here rather than directly from the background worker.
     */
    private fun applyKioskState() {
        val dpm = getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        val isDeviceOwner = dpm.isDeviceOwnerApp(packageName)
        if (!isDeviceOwner) return

        val shouldBeInKiosk = Prefs.of(this).kioskEnabled

        if (shouldBeInKiosk) {
            startLockTask()
        } else {
            stopLockTask()
        }
    }

    private fun updateStatus() {
        val prefs = Prefs.of(this)
        statusText.text = when {
            prefs.deviceToken != null -> getString(R.string.status_enrolled)
            prefs.pendingEnrollmentToken != null -> getString(R.string.status_enrolling)
            else -> getString(R.string.status_not_provisioned)
        }
    }
}
