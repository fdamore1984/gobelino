package com.gobelino.agent.worker

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.os.Build
import com.gobelino.agent.receiver.AgentDeviceAdminReceiver
import com.gobelino.agent.util.Prefs
import org.json.JSONObject

/**
 * Executes a single command queued by the admin panel. All of these
 * rely on Device Owner privileges granted purely by the native QR
 * provisioning flow — no Android Enterprise APIs involved.
 */
object CommandExecutor {

    fun execute(context: Context, command: JSONObject) {
        val id = command.getInt("id")
        val type = command.getString("type")
        val payload = command.optJSONObject("payload")

        val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        val admin = AgentDeviceAdminReceiver.componentName(context)

        try {
            when (type) {
                "lock" -> dpm.lockNow()

                "reboot" -> {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) dpm.reboot(admin)
                }

                "wipe" -> dpm.wipeData(0)

                "set_kiosk" -> {
                    val enabled = payload?.optBoolean("enabled", false) ?: false
                    Prefs.of(context).kioskEnabled = enabled
                    // MainActivity reads this flag and starts/stops
                    // startLockTask()/stopLockTask() next time it's
                    // in the foreground (LockTask can only be toggled
                    // from a running, visible activity).
                }

                "apply_policy" -> {
                    // Extension point: e.g. payload could carry
                    // camera-disabled, allowed app list, etc.
                }

                else -> {
                    CommandResultStore.enqueue(context, id, "failed", "unknown_command_type")
                    CommandHistoryStore.record(context, id, type, "failed", "unknown_command_type")
                    return
                }
            }

            CommandResultStore.enqueue(context, id, "acked", null)
            CommandHistoryStore.record(context, id, type, "acked", null)
        } catch (e: Exception) {
            CommandResultStore.enqueue(context, id, "failed", e.message)
            CommandHistoryStore.record(context, id, type, "failed", e.message)
        }
    }
}
