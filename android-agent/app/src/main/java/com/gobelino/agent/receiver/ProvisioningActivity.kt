package com.gobelino.agent.receiver

import android.app.Activity
import android.app.admin.DevicePolicyManager
import android.os.Bundle

class ProvisioningActivity : Activity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        when (intent?.action) {
            DevicePolicyManager.ACTION_GET_PROVISIONING_MODE -> {
                val result = intent.getParcelableExtra<android.content.Intent>(
                    DevicePolicyManager.EXTRA_PROVISIONING_MODE
                ) ?: android.content.Intent()
                result.putExtra(
                    DevicePolicyManager.EXTRA_PROVISIONING_MODE,
                    DevicePolicyManager.PROVISIONING_MODE_FULLY_MANAGED_DEVICE
                )
                setResult(RESULT_OK, result)
            }
            DevicePolicyManager.ACTION_ADMIN_POLICY_COMPLIANCE -> {
                setResult(RESULT_OK)
            }
        }
        finish()
    }
}