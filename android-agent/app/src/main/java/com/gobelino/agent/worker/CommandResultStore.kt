package com.gobelino.agent.worker

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject

/**
 * Command execution and the next poll don't happen in the same
 * request (the command arrives IN a poll response, gets executed
 * right away, but its result is only reported at the FOLLOWING
 * poll). This tiny persistent queue bridges the two.
 */
object CommandResultStore {
    private const val PREFS = "agent_command_results"
    private const val KEY = "pending_results"

    @Synchronized
    fun enqueue(context: Context, commandId: Int, status: String, result: String?) {
        val sp = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val current = JSONArray(sp.getString(KEY, "[]"))

        current.put(JSONObject().apply {
            put("id", commandId)
            put("status", status)
            if (result != null) put("result", result)
        })

        sp.edit().putString(KEY, current.toString()).apply()
    }

    /** Returns all queued results as a list of JSON objects and clears the queue. */
    @Synchronized
    fun drain(context: Context): List<JSONObject> {
        val sp = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val current = JSONArray(sp.getString(KEY, "[]"))
        sp.edit().remove(KEY).apply()

        return (0 until current.length()).map { current.getJSONObject(it) }
    }
}
