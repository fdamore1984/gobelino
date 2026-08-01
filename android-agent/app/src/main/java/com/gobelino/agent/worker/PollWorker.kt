package com.gobelino.agent.worker

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.os.BatteryManager
import android.os.Build
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.gobelino.agent.BuildConfig
import com.gobelino.agent.net.ApiClient
import com.gobelino.agent.receiver.AgentDeviceAdminReceiver
import com.gobelino.agent.util.Prefs
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject

/**
 * This is the "no FCM" heart of the agent: instead of waiting for a
 * push, we wake up on our own schedule, report status, execute any
 * command the backend queued for us, and report back the outcome —
 * all in the same request. See DeviceAgentController@poll.
 *
 * Scheduling is a self-chaining OneTimeWorkRequest (see
 * PollScheduler), not a PeriodicWorkRequest: this method always
 * reschedules the next run itself, in a `finally`, so it keeps going
 * whether this run succeeded, failed, or wasn't enrolled yet. If we
 * just executed commands, the next check-in is scheduled almost
 * immediately instead of waiting a full interval, so the admin panel
 * sees the "acked" result quickly instead of after a full cycle.
 */
class PollWorker(appContext: Context, params: WorkerParameters) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val prefs = Prefs.of(applicationContext)
        var nextDelaySeconds = prefs.pollIntervalSeconds

        // Idempotente: applica l'esenzione anche sui dispositivi gia'
        // enrollati prima di questa modifica, non appena ricevono
        // l'APK aggiornata — non serve ri-registrarli da zero.
        val dpm = applicationContext.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        AgentDeviceAdminReceiver.exemptFromBatteryOptimizations(applicationContext, dpm)

        try {
            val serverUrl = prefs.serverUrl ?: return@withContext Result.success()
            val api = ApiClient(serverUrl)

            // Not yet enrolled: consume the pending token from the QR first.
            if (prefs.deviceToken == null) {
                val pendingToken = prefs.pendingEnrollmentToken ?: return@withContext Result.success()
                val response = api.enroll(pendingToken, buildDeviceInfo())
                prefs.deviceToken = response.getString("device_token")
                prefs.pollIntervalSeconds = response.optInt("poll_interval_seconds", 300)
                prefs.pendingEnrollmentToken = null
            }

            val status = buildDeviceInfo().apply {
                put("battery_level", batteryLevel())
                put("command_results", JSONArray(CommandResultStore.drain(applicationContext)))
            }

            val response = api.poll(prefs.deviceToken!!, status)

            prefs.pollIntervalSeconds = response.optInt("poll_interval_seconds", prefs.pollIntervalSeconds)
            prefs.kioskEnabled = response.optBoolean("kiosk_enabled", false)
            nextDelaySeconds = prefs.pollIntervalSeconds

            val commands = response.optJSONArray("commands") ?: JSONArray()
            for (i in 0 until commands.length()) {
                CommandExecutor.execute(applicationContext, commands.getJSONObject(i))
            }

            // We just executed command(s): don't wait a full interval to
            // report the "acked" result back, do it on the very next tick.
            if (commands.length() > 0) {
                nextDelaySeconds = FAST_FOLLOWUP_SECONDS
            }

            Result.success()
        } catch (e: Exception) {
            // Swallowed on purpose: the next scheduled run (below) is our
            // retry mechanism, we don't want WorkManager's own backoff
            // fighting with our chain.
            Result.success()
        } finally {
            PollScheduler.scheduleNext(applicationContext, nextDelaySeconds)
        }
    }

    private fun buildDeviceInfo(): JSONObject = JSONObject().apply {
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

    private fun batteryLevel(): Int {
        val bm = applicationContext.getSystemService(Context.BATTERY_SERVICE) as? BatteryManager
        return bm?.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY) ?: -1
    }

    companion object {
        private const val FAST_FOLLOWUP_SECONDS = 15
    }
}
