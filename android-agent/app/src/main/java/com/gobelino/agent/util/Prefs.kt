package com.gobelino.agent.util

import android.content.Context

class Prefs private constructor(context: Context) {
    private val sp = context.getSharedPreferences("agent_prefs", Context.MODE_PRIVATE)

    companion object {
        @Volatile private var instance: Prefs? = null
        fun of(context: Context): Prefs =
            instance ?: synchronized(this) {
                instance ?: Prefs(context.applicationContext).also { instance = it }
            }
    }

    var serverUrl: String?
        get() = sp.getString("server_url", null)
        set(value) = sp.edit().putString("server_url", value).apply()

    /** One-time token from the QR, consumed by the first successful /enroll call. */
    var pendingEnrollmentToken: String?
        get() = sp.getString("pending_enrollment_token", null)
        set(value) = sp.edit().putString("pending_enrollment_token", value).apply()

    /** Permanent per-device secret returned by /enroll, used on every /poll. */
    var deviceToken: String?
        get() = sp.getString("device_token", null)
        set(value) = sp.edit().putString("device_token", value).apply()

    var pollIntervalSeconds: Int
        get() = sp.getInt("poll_interval_seconds", 300)
        set(value) = sp.edit().putInt("poll_interval_seconds", value).apply()

    var kioskEnabled: Boolean
        get() = sp.getBoolean("kiosk_enabled", false)
        set(value) = sp.edit().putBoolean("kiosk_enabled", value).apply()

    fun registerChangeListener(listener: android.content.SharedPreferences.OnSharedPreferenceChangeListener) {
        sp.registerOnSharedPreferenceChangeListener(listener)
    }

    fun unregisterChangeListener(listener: android.content.SharedPreferences.OnSharedPreferenceChangeListener) {
        sp.unregisterOnSharedPreferenceChangeListener(listener)
    }

    /**
     * Timestamp (epoch millis) dell'ultimo giro completato dalla
     * PollForegroundService. Il watchdog in PollWorker lo confronta
     * con "adesso" per capire se la foreground service e' ancora viva
     * senza dover interrogare ActivityManager.
     */
    var lastHeartbeatAtMillis: Long
        get() = sp.getLong("last_heartbeat_at_millis", 0L)
        set(value) = sp.edit().putLong("last_heartbeat_at_millis", value).apply()
}