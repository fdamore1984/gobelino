package com.gobelino.agent.receiver

import android.app.Activity
import android.app.admin.DevicePolicyManager
import android.os.Bundle
import com.gobelino.agent.util.Prefs

class ProvisioningActivity : Activity() {

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
                // Nessuna richiesta di esenzione batteria per ora: si
                // riprende in un secondo momento, in modo isolato e più
                // sicuro, dopo aver capito perché mandava in crash il
                // provisioning su alcuni device.
                setResult(RESULT_OK)
                finish()
            }
            else -> finish()
        }
    }
}
