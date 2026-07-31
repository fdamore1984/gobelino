package com.gobelino.agent.receiver

import android.app.admin.DeviceAdminReceiver
import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.os.Bundle
import com.gobelino.agent.util.Prefs
import com.gobelino.agent.worker.PollScheduler

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

        // Finalizes Device Owner / Profile Owner activation.
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

        val isDeviceOwner = dpm.isDeviceOwnerApp(context.packageName)

        // Il kiosk/lock-task ha senso solo per Device Owner (device intero).
        if (isDeviceOwner) {
            dpm.setLockTaskPackages(admin, arrayOf(context.packageName))
        } else {
            // Caso Work Profile: questo callback gira DENTRO il profilo
            // di lavoro appena creato. Apriamo subito la nostra
            // MainActivity qui, cosi' l'utente si ritrova l'app aperta
            // nel profilo di lavoro senza doverla cercare a mano.
            val launchIntent = Intent(context, com.gobelino.agent.MainActivity::class.java).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            context.startActivity(launchIntent)
        }

        schedulePolling(context)
    }

    override fun onEnabled(context: Context, intent: Intent) {
        schedulePolling(context)
    }

    private fun schedulePolling(context: Context) {
        PollScheduler.scheduleNow(context)
    }
}
