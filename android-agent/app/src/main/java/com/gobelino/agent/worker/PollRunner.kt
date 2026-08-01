package com.gobelino.agent.worker

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.os.BatteryManager
import android.os.Build
import com.gobelino.agent.BuildConfig
import com.gobelino.agent.net.ApiClient
import com.gobelino.agent.receiver.AgentDeviceAdminReceiver
import com.gobelino.agent.util.Prefs
import org.json.JSONArray
import org.json.JSONObject

/**
 * The actual "no FCM" check-in logic: report status, execute any
 * command the backend queued for us, report back the outcome — all
 * in the same request. See DeviceAgentController@poll.
 *
 * Extracted out of PollWorker so both the WorkManager watchdog
 * (PollWorker, infrequent safety net) and PollForegroundService
 * (the actual continuous poller, survives Doze while running) share
 * one implementation instead of drifting apart over time.
 */
object PollRunner {

    /** Runs one check-in cycle. Returns the delay (seconds) the caller
     *  should wait before the next one; never throws. */
    suspend fun runOnce(context: Context): Int {
        val prefs = Prefs.of(context)
        var nextDelaySeconds = prefs.pollIntervalSeconds

        // Idempotente: applica esenzione batteria + permesso notifiche
        // anche sui dispositivi gia' enrollati prima di questa
        // modifica, non appena ricevono l'APK aggiornata — non serve
        // ri-registrarli da zero.
        val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        AgentDeviceAdminReceiver.exemptFromBatteryOptimizations(context, dpm)
        AgentDeviceAdminReceiver.grantNotificationPermission(context, dpm)

        try {
            val serverUrl = prefs.serverUrl ?: return nextDelaySeconds

            val api = ApiClient(serverUrl)

            // Not yet enrolled: consume the pending token from the QR first.
            if (prefs.deviceToken == null) {
                val pendingToken = prefs.pendingEnrollmentToken ?: return nextDelaySeconds
                val response = api.enroll(pendingToken, buildDeviceInfo())
                prefs.deviceToken = response.getString("device_token")
                prefs.pollIntervalSeconds = response.optInt("poll_interval_seconds", 300)
                prefs.pendingEnrollmentToken = null
            }

            val status = buildDeviceInfo(context).apply {
                put("battery_level", batteryLevel(context))
                put("command_results", JSONArray(CommandResultStore.drain(context)))
            }

            val response = api.poll(prefs.deviceToken!!, status)

            prefs.pollIntervalSeconds = response.optInt("poll_interval_seconds", prefs.pollIntervalSeconds)
            prefs.kioskEnabled = response.optBoolean("kiosk_enabled", false)
            nextDelaySeconds = prefs.pollIntervalSeconds

            val commands = response.optJSONArray("commands") ?: JSONArray()
            for (i in 0 until commands.length()) {
                CommandExecutor.execute(context, commands.getJSONObject(i))
            }

            // We just executed command(s): don't wait a full interval to
            // report the "acked" result back, do it on the very next tick.
            if (commands.length() > 0) {
                nextDelaySeconds = FAST_FOLLOWUP_SECONDS
            }
        } catch (e: Exception) {
            // Swallowed on purpose: the next scheduled run is our retry
            // mechanism, we don't want an exception here to take down
            // the caller's loop (Worker or foreground service).
        }

        prefs.lastHeartbeatAtMillis = System.currentTimeMillis()
        return nextDelaySeconds
    }

    private fun buildDeviceInfo(context: Context): JSONObject = JSONObject().apply {
        put("model", Build.MODEL)
        put("manufacturer", Build.MANUFACTURER)
        put("android_version", Build.VERSION.RELEASE)
        put("agent_app_version", BuildConfig.VERSION_NAME)
        serialNumber()?.let { put("serial_number", it) }
    }

    /**
     * Build.getSerial() needs no extra permission when the caller is
     * the active Device Owner (our case) — it's exempted by the OS.
     * Still guarded, since it can throw/return UNKNOWN on some OEMs.
     */
    private fun serialNumber(): String? = try {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Build.getSerial().takeIf { it != Build.UNKNOWN }
        } else {
            @Suppress("DEPRECATION")
            Build.SERIAL.takeIf { it != Build.UNKNOWN }
        }
    } catch (e: SecurityException) {
        null
    }

    private fun batteryLevel(context: Context): Int {
        val bm = context.getSystemService(Context.BATTERY_SERVICE) as? BatteryManager
        return bm?.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY) ?: -1
    }

    const val FAST_FOLLOWUP_SECONDS = 15
}
