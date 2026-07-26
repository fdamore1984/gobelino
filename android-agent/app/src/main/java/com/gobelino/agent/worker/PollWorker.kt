package com.gobelino.agent.worker

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.os.BatteryManager
import android.os.Build
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import com.gobelino.agent.BuildConfig
import com.gobelino.agent.net.ApiClient
import com.gobelino.agent.receiver.AgentDeviceAdminReceiver
import com.gobelino.agent.util.Prefs
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.util.concurrent.TimeUnit

/**
 * This is the "no FCM" heart of the agent: instead of waiting for a
 * push, we wake up on our own schedule, report status, execute any
 * command the backend queued for us, and report back the outcome —
 * all in the same request. See DeviceAgentController@poll.
 */
class PollWorker(appContext: Context, params: WorkerParameters) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val prefs = Prefs.of(applicationContext)
        val serverUrl = prefs.serverUrl ?: return@withContext Result.failure()
        val api = ApiClient(serverUrl)

        try {
            // Not yet enrolled: consume the pending token from the QR first.
            if (prefs.deviceToken == null) {
                val pendingToken = prefs.pendingEnrollmentToken ?: return@withContext Result.failure()
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

            val newInterval = response.optInt("poll_interval_seconds", prefs.pollIntervalSeconds)
            if (newInterval != prefs.pollIntervalSeconds) {
                prefs.pollIntervalSeconds = newInterval
                rescheduleWithNewInterval(newInterval)
            }
            prefs.kioskEnabled = response.optBoolean("kiosk_enabled", false)

            val commands = response.optJSONArray("commands") ?: JSONArray()
            for (i in 0 until commands.length()) {
                CommandExecutor.execute(applicationContext, commands.getJSONObject(i))
            }

            Result.success()
        } catch (e: Exception) {
            Result.retry()
        }
    }

    private fun buildDeviceInfo(): JSONObject = JSONObject().apply {
        put("model", Build.MODEL)
        put("manufacturer", Build.MANUFACTURER)
        put("android_version", Build.VERSION.RELEASE)
        put("agent_app_version", BuildConfig.VERSION_NAME)
    }

    private fun batteryLevel(): Int {
        val bm = applicationContext.getSystemService(Context.BATTERY_SERVICE) as? BatteryManager
        return bm?.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY) ?: -1
    }

    private fun rescheduleWithNewInterval(seconds: Int) {
        val request = PeriodicWorkRequestBuilder<PollWorker>(seconds.coerceAtLeast(60).toLong(), TimeUnit.SECONDS).build()
        WorkManager.getInstance(applicationContext).enqueueUniquePeriodicWork(
            "agent-poll",
            ExistingPeriodicWorkPolicy.UPDATE,
            request
        )
    }
}
