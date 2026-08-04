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

        /**
         * Il "no FCM" polling in background dipende da WorkManager, che
         * su Doze/App Standby (e sugli scheduler aggressivi di molti
         * OEM) puo' essere ritardato o sospeso quando il device e'
         * inattivo — a differenza di un'azione interattiva come "forza
         * connessione". setUserControlDisabledPackages (Device Owner,
         * API 30+) protegge l'app da queste restrizioni senza mostrare
         * alcun dialog all'utente. Su API < 30 non esiste equivalente
         * silenzioso: li' l'unica strada e' il dialog
         * ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS, che pero'
         * richiede un tap dell'utente. Chiamabile ripetutamente: e'
         * idempotente.
         */
        fun exemptFromBatteryOptimizations(context: Context, dpm: DevicePolicyManager) {
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.R
                && dpm.isDeviceOwnerApp(context.packageName)
            ) {
                try {
                    dpm.setUserControlDisabledPackages(componentName(context), listOf(context.packageName))
                } catch (e: Exception) {
                    // Non fatale: il polling continua comunque, solo con
                    // latenza meno affidabile in background.
                }
            }
        }

        /**
         * Dal API 33 (Tiramisu) le notifiche richiedono un permesso
         * runtime esplicito, altrimenti startForeground() funziona ma
         * la notifica resta invisibile. Come Device Owner possiamo
         * concederlo direttamente, senza dialog per l'utente — coerente
         * col resto dell'agent, pensato per operare senza interazione.
         */
        fun grantNotificationPermission(context: Context, dpm: DevicePolicyManager) {
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU
                && dpm.isDeviceOwnerApp(context.packageName)
            ) {
                try {
                    dpm.setPermissionGrantState(
                        componentName(context),
                        context.packageName,
                        android.Manifest.permission.POST_NOTIFICATIONS,
                        DevicePolicyManager.PERMISSION_GRANT_STATE_GRANTED
                    )
                } catch (e: Exception) {
                    // Non fatale: su API 33+ senza questo la notifica
                    // della foreground service resta invisibile, ma il
                    // servizio e il polling continuano a funzionare.
                }
            }
        }
    }

    override fun onProfileProvisioningComplete(context: Context, intent: Intent) {
        val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        val admin = componentName(context)

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
            exemptFromBatteryOptimizations(context, dpm)
            grantNotificationPermission(context, dpm)
        } else {
            // Caso Work Profile: questo callback gira DENTRO il profilo
            // di lavoro appena creato, che parte disabilitato finche' non
            // lo si abilita esplicitamente — a differenza del Device
            // Owner, dove non esiste alcun "profilo" da abilitare (da qui
            // veniva il crash: chiamare setProfileEnabled() fuori da un
            // profilo di lavoro lancia SecurityException).
            try {
                dpm.setProfileEnabled(admin)
            } catch (e: Exception) {
                // Non fatale: se fallisce restiamo comunque con l'app
                // avviabile a mano dal profilo di lavoro.
            }

            // Apriamo subito la nostra MainActivity qui, cosi' l'utente
            // si ritrova l'app aperta nel profilo di lavoro senza doverla
            // cercare a mano.
            val launchIntent = Intent(context, com.gobelino.agent.MainActivity::class.java).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            context.startActivity(launchIntent)
        }

        schedulePolling(context)
    }

    override fun onEnabled(context: Context, intent: Intent) {
        // Copre anche i dispositivi gia' provisionati prima
        // dell'introduzione di questa esenzione: onEnabled riparte ad
        // ogni boot/riabilitazione dell'admin, non solo al primo setup.
        val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        exemptFromBatteryOptimizations(context, dpm)
        grantNotificationPermission(context, dpm)
        schedulePolling(context)
    }

    private fun schedulePolling(context: Context) {
        PollScheduler.scheduleNow(context)
    }
}
