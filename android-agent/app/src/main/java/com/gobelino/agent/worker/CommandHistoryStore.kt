package com.gobelino.agent.worker

import android.content.Context
import com.gobelino.agent.util.deviceProtected
import com.gobelino.agent.util.migrateToDeviceProtected
import org.json.JSONArray
import org.json.JSONObject

/**
 * Persistent log of every command received from the server, shown in
 * MainActivity. Distinct from CommandResultStore, which is a transient
 * outbox that gets drained on the next /poll — this one is purely for
 * the on-device UI and keeps a rolling window of recent entries.
 */
object CommandHistoryStore {
    private const val PREFS = "agent_command_history"
    private const val KEY = "history"
    private const val MAX_ENTRIES = 30

    private fun prefs(context: Context) = context.run {
        migrateToDeviceProtected(PREFS)
        deviceProtected().getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    }

    data class Entry(
        val id: Int,
        val type: String,
        val status: String,
        val result: String?,
        val receivedAtMillis: Long
    )

    @Synchronized
    fun record(context: Context, id: Int, type: String, status: String, result: String?) {
        val sp = prefs(context)
        val current = JSONArray(sp.getString(KEY, "[]"))

        val entry = JSONObject().apply {
            put("id", id)
            put("type", type)
            put("status", status)
            if (result != null) put("result", result)
            put("received_at", System.currentTimeMillis())
        }

        // Prepend: most recent first. JSONArray has no insert-at-0, so rebuild.
        val updated = JSONArray()
        updated.put(entry)
        for (i in 0 until current.length()) updated.put(current.getJSONObject(i))

        // Cap the log so it doesn't grow unbounded.
        val trimmed = JSONArray()
        for (i in 0 until minOf(updated.length(), MAX_ENTRIES)) trimmed.put(updated.getJSONObject(i))

        sp.edit().putString(KEY, trimmed.toString()).apply()
    }

    @Synchronized
    fun all(context: Context): List<Entry> {
        val sp = prefs(context)
        val current = JSONArray(sp.getString(KEY, "[]"))

        return (0 until current.length()).map {
            val o = current.getJSONObject(it)
            Entry(
                id = o.getInt("id"),
                type = o.getString("type"),
                status = o.getString("status"),
                result = o.optString("result", null),
                receivedAtMillis = o.optLong("received_at", 0L)
            )
        }
    }
}
