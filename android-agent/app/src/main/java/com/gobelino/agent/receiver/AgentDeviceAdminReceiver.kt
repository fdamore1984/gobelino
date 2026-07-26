package com.gobelino.agent.receiver

import android.app.admin.DeviceAdminReceiver
import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.os.Bundle
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import com.gobelino.agent.util.Prefs
import com.gobelino.agent.worker.PollWorker
import java.util.concurrent.TimeUnit

/**
 * This is the actual "Device Owner" hook. Android sets this app as
 * Device Owner natively (ACTION_PROVISIONING) the moment the person
 * scans our QR code during setup — no Android Enterprise / Google
 * EMM binding of any kind is involved.
 */
class AgentDeviceAdminReceiver : DeviceAdminReceiver() {

    companion object {
        fun componentName(context: Context): ComponentName =
            ComponentName(context, AgentDeviceAdminReceiver::class.java)
    }

    override fun onProfileProvisioningComplete(context: Context, intent: Intent) {
        val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        val admin = componentName(context)

        // Finalizes Device Owner activation.
        dpm.setProfileEnabled(admin)

        // Extras we embedded in the provisioning QR (see
        // AndroidAgentService::createEnrollmentToken on the backend).
        val extras: Bundle? = intent.getBundleExtra(
            "android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE"
        )
        val serverUrl = extras?.getString("server_url")
        val enrollmentToken = extras?.getString("enrollment_token")

        if (serverUrl != null && enrollmentToken != null) {
            Prefs.of(context).apply {
                this.serverUrl = serverUrl
                this.pendingEnrollmentToken = enrollmentToken
            }
        }

        // Lets the agent's own MainActivity own the kiosk lock task
        // allowlist (updated dynamically from backend policy).
        dpm.setLockTaskPackages(admin, arrayOf(context.packageName))

        schedulePolling(context)
    }

    override fun onEnabled(context: Context, intent: Intent) {
        schedulePolling(context)
    }

    private fun schedulePolling(context: Context) {
        val interval = Prefs.of(context).pollIntervalSeconds.coerceAtLeast(60)

        val request = PeriodicWorkRequestBuilder<PollWorker>(interval.toLong(), TimeUnit.SECONDS)
            .build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            "agent-poll",
            ExistingPeriodicWorkPolicy.UPDATE,
            request
        )
    }
}
