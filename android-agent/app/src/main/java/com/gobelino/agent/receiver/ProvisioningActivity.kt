package com.gobelino.agent.receiver

import android.app.Activity
import android.app.admin.DevicePolicyManager
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import android.util.Log
import com.gobelino.agent.util.Prefs

class ProvisioningActivity : Activity() {

    companion object {
        private const val TAG = "ProvisioningActivity"
        private const val REQUEST_IGNORE_BATTERY_OPTIMIZATIONS = 3001
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        try {
            dispatch()
        } catch (e: Throwable) {
            // Qualunque cosa vada storta qui non deve MAI far crashare
            // il provisioning: è uno step best-effort (chiedere
            // un'esenzione), non deve poter bloccare l'arruolamento del
            // dispositivo. Logghiamo e chiudiamo puliti.
            Log.e(TAG, "provisioning step failed, continuing anyway", e)
            runCatching { setResult(RESULT_OK) }
            finish()
        }
    }

    private fun dispatch() {
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
            // Verifica che qualcosa possa davvero gestire l'intent prima
            // di lanciarlo: su alcune build AOSP custom (es. Teclast)
            // l'azione non è risolvibile, e su alcuni OEM chiamare
            // startActivityForResult su un intent non risolvibile lancia
            // un'eccezione diversa da ActivityNotFoundException (o crasha
            // in modo non catturabile più a valle). Meglio controllare
            // prima con resolveActivity ed evitare del tutto la chiamata
            // se non c'è nessuno che risponde.
            if (intent.resolveActivity(packageManager) == null) {
                Log.w(TAG, "no activity resolves ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS on this build")
                setResult(RESULT_OK)
                finish()
                return
            }

            Prefs.of(this).batteryOptimizationAskedAtMillis = System.currentTimeMillis()
            startActivityForResult(intent, REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
        } catch (e: Exception) {
            // Non solo ActivityNotFoundException: a seconda di OEM/versione
            // può arrivare SecurityException (restrizioni Background
            // Activity Launch su Android 14+/Samsung) o altro. In ogni
            // caso non blocchiamo il provisioning per questo: il fallback
            // in MainActivity ritenta più avanti.
            Log.w(TAG, "failed to request battery optimization exemption", e)
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
