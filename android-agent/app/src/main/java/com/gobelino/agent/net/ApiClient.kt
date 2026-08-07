package com.gobelino.agent.net

import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

private val JSON = "application/json; charset=utf-8".toMediaType()

/**
 * Thin wrapper around the two endpoints exposed by
 * routes/api.php + Api\DeviceAgentController on the backend.
 */
class ApiClient(private val baseUrl: String) {

    private val http = OkHttpClient.Builder()
        .connectTimeout(20, TimeUnit.SECONDS)
        .readTimeout(20, TimeUnit.SECONDS)
        .build()

    // /poll is now long-polling: the server can hold the request open
    // up to ~25s (DeviceWakeupService::LONG_POLL_TIMEOUT_SECONDS)
    // waiting for a command instead of returning immediately. This
    // client needs a read timeout comfortably above that, or we'd cut
    // the connection ourselves before the server ever gets to answer.
    private val pollHttp = http.newBuilder()
        .readTimeout(35, TimeUnit.SECONDS)
        .build()

    /** POST /api/agent/enroll — trades the one-time QR token for a device_token. */
    fun enroll(enrollmentToken: String, deviceInfo: JSONObject): JSONObject {
        deviceInfo.put("enrollment_token", enrollmentToken)

        val request = Request.Builder()
            .url("$baseUrl/api/agent/enroll")
            .post(deviceInfo.toString().toRequestBody(JSON))
            .build()

        http.newCall(request).execute().use { response ->
            val body = response.body?.string().orEmpty()
            if (!response.isSuccessful) throw ApiException(response.code, body)
            return JSONObject(body)
        }
    }

    /** POST /api/agent/poll — heartbeat + fetch/ack of commands. */
    fun poll(deviceToken: String, status: JSONObject): JSONObject {
        val request = Request.Builder()
            .url("$baseUrl/api/agent/poll")
            .addHeader("X-Device-Token", deviceToken)
            .post(status.toString().toRequestBody(JSON))
            .build()

        pollHttp.newCall(request).execute().use { response ->
            val body = response.body?.string().orEmpty()
            if (!response.isSuccessful) throw ApiException(response.code, body)
            return JSONObject(body)
        }
    }

    /** POST /api/agent/fcm-token — called whenever FCM (re)generates the token. */
    fun updateFcmToken(deviceToken: String, fcmToken: String) {
        val body = JSONObject().put("fcm_token", fcmToken)

        val request = Request.Builder()
            .url("$baseUrl/api/agent/fcm-token")
            .addHeader("X-Device-Token", deviceToken)
            .post(body.toString().toRequestBody(JSON))
            .build()

        http.newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                throw ApiException(response.code, response.body?.string().orEmpty())
            }
        }
    }
}

class ApiException(val code: Int, message: String) : Exception("HTTP $code: $message")
