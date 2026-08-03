package com.gobelino.agent.receiver

import android.app.Activity
import android.app.admin.DevicePolicyManager
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import com.gobelino.agent.util.Prefs

class ProvisioningActivity : Activity() {

    companion object {
        private const val REQUEST_IGNORE_BATTERY_OPTIMIZATIONS = 3001
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        when (intent?.action) {
            DevicePolicyManager.ACTION_GET_PROVISIONING_MODE -> {
                // Salva subito gli extra: qui sono garantiti presenti.
                val extras = intent.getParcelableExtra<android.os.PersistableBundle>(
                    DevicePolicyManager.EXTRA_PROVISIONING_ADMIN_EXTRAS_BUNDLE
                )
                extras?.getString("server_url")?.let { Prefs.of(this).serverUrl = it }
                extras?.getString("enrollment_token")?.let { Prefs.of(this).pendingEnrollmentToken = it }

                val result = intent.getParcelableExtra<android.content.Intent>(
                    DevicePolicyManager.EXTRA_PROVISIONING_MODE
                ) ?: android.content.Intent()
                result.putExtra(
                    DevicePolicyManager.EXTRA_PROVISIONING_MODE,
                    DevicePolicyManager.PROVISIONING_MODE_FULLY_MANAGED_DEVICE
                )
                setResult(RESULT_OK, result)
                finish()
            }
            DevicePolicyManager.ACTION_ADMIN_POLICY_COMPLIANCE -> {
                // Ultimo step del provisioning, prima che il controllo torni
                // all'app: momento giusto (e unico "gratuito", un solo tap)
                // per chiedere l'esenzione dall'ottimizzazione batteria. Se
                // non richiesta qui, il sistema mette l'app in standby
                // bucket "rare" dopo il primo riavvio, bloccando alarm/job
                // di recovery (vedi BootRecoveryReceiver).
                requestIgnoreBatteryOptimizationsThenFinish()
            }
            else -> finish()
        }
    }

    private fun requestIgnoreBatteryOptimizationsThenFinish() {
        val pm = getSystemService(POWER_SERVICE) as PowerManager
        if (pm.isIgnoringBatteryOptimizations(packageName)) {
            // Già esente (es. impostato da un provisioning precedente, o
            // OEM che la garantisce di suo per i Device Owner): niente
            // dialogo da mostrare.
            setResult(RESULT_OK)
            finish()
            return
        }

        try {
            val intent = Intent(
                Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS,
                Uri.parse("package:$packageName")
            )
            startActivityForResult(intent, REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
        } catch (e: android.content.ActivityNotFoundException) {
            // Build che non espone questo dialogo: non blocchiamo il
            // provisioning per questo, il fallback in MainActivity
            // ritenterà al primo avvio in foreground.
            setResult(RESULT_OK)
            finish()
        }
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode == REQUEST_IGNORE_BATTERY_OPTIMIZATIONS) {
            // Indipendentemente dall'esito (l'utente potrebbe in teoria
            // rifiutare) non blocchiamo il completamento del provisioning:
            // il fallback in MainActivity ripropone la richiesta se serve.
            setResult(RESULT_OK)
            finish()
        }
    }
}
