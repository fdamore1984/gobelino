package com.gobelino.agent

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.os.Bundle
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.ListView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.Observer
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkInfo
import androidx.work.WorkManager
import com.gobelino.agent.util.Prefs
import com.gobelino.agent.worker.CommandHistoryStore
import com.gobelino.agent.worker.PollScheduler
import com.gobelino.agent.worker.PollWorker
import java.text.DateFormat
import java.util.Date
import java.util.UUID
import java.util.concurrent.TimeUnit

/**
 * Launcher activity. In normal operation the device just sits on
 * this screen (or, in kiosk mode, is pinned to it via LockTask) while
 * PollWorker does all the real work in the background. It also shows
 * the log of commands received from the server and lets the user
 * force an immediate check-in instead of waiting for the next poll.
 */
class MainActivity : AppCompatActivity() {

    private lateinit var statusText: TextView
    private lateinit var forceSyncButton: Button
    private lateinit var commandsEmptyText: TextView
    private lateinit var commandsList: ListView
    private lateinit var commandsAdapter: ArrayAdapter<String>

    private var pendingSyncId: UUID? = null
    private val syncObserver = Observer<WorkInfo?> { info ->
        if (info == null) return@Observer
        if (info.state.isFinished) {
            forceSyncButton.isEnabled = true
            forceSyncButton.setText(R.string.action_force_sync)
            Toast.makeText(
                this,
                if (info.state == WorkInfo.State.SUCCEEDED) R.string.sync_success else R.string.sync_failed,
                Toast.LENGTH_SHORT
            ).show()
            updateStatus()
            refreshCommandsList()
            pendingSyncId?.let { WorkManager.getInstance(this).getWorkInfoByIdLiveData(it).removeObserver(syncObserver) }
            pendingSyncId = null
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        statusText = findViewById(R.id.statusText)
        forceSyncButton = findViewById(R.id.forceSyncButton)
        commandsEmptyText = findViewById(R.id.commandsEmptyText)
        commandsList = findViewById(R.id.commandsList)

        commandsAdapter = ArrayAdapter(this, android.R.layout.simple_list_item_1, mutableListOf())
        commandsList.adapter = commandsAdapter

        forceSyncButton.setOnClickListener { forceSync() }

        ensurePollingScheduled()
        updateStatus()
        refreshCommandsList()
    }

    override fun onResume() {
        super.onResume()
        applyKioskState()
        updateStatus()
        refreshCommandsList()
    }

    private fun ensurePollingScheduled() {
        val prefs = Prefs.of(this)
        if (prefs.serverUrl == null) return // not provisioned yet

        val interval = prefs.pollIntervalSeconds.coerceAtLeast(60)
        val request = PeriodicWorkRequestBuilder<PollWorker>(interval.toLong(), TimeUnit.SECONDS).build()

        WorkManager.getInstance(this).enqueueUniquePeriodicWork(
            "agent-poll",
            ExistingPeriodicWorkPolicy.KEEP,
            request
        )
    }

    /** Triggered by the "Forza connessione" button: runs a check-in right away. */
    private fun forceSync() {
        if (Prefs.of(this).serverUrl == null) {
            Toast.makeText(this, R.string.status_not_provisioned, Toast.LENGTH_SHORT).show()
            return
        }

        forceSyncButton.isEnabled = false
        forceSyncButton.setText(R.string.action_syncing)

        val id = PollScheduler.forceNow(this)
        pendingSyncId = id
        WorkManager.getInstance(this).getWorkInfoByIdLiveData(id).observe(this, syncObserver)
    }

    /**
     * Kiosk mode uses Android's LockTask API: only possible because
     * this app is Device Owner and was whitelisted via
     * setLockTaskPackages() in AgentDeviceAdminReceiver. It can only
     * be started/stopped from a foreground activity, hence checking
     * the flag here rather than directly from the background worker.
     */
    private fun applyKioskState() {
        val dpm = getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        val isDeviceOwner = dpm.isDeviceOwnerApp(packageName)
        if (!isDeviceOwner) return

        val shouldBeInKiosk = Prefs.of(this).kioskEnabled

        if (shouldBeInKiosk) {
            startLockTask()
        } else {
            stopLockTask()
        }
    }

    private fun updateStatus() {
        val prefs = Prefs.of(this)
        statusText.text = when {
            prefs.deviceToken != null -> getString(R.string.status_enrolled)
            prefs.pendingEnrollmentToken != null -> getString(R.string.status_enrolling)
            else -> getString(R.string.status_not_provisioned)
        }
    }

    private fun refreshCommandsList() {
        val entries = CommandHistoryStore.all(this)
        val dateFormat = DateFormat.getDateTimeInstance(DateFormat.SHORT, DateFormat.SHORT)

        commandsAdapter.clear()
        commandsAdapter.addAll(entries.map { entry ->
            val `when` = if (entry.receivedAtMillis > 0) dateFormat.format(Date(entry.receivedAtMillis)) else ""
            getString(R.string.command_entry_format, entry.id, entry.type, "${entry.status} · $`when`")
        })

        commandsEmptyText.visibility = if (entries.isEmpty()) android.view.View.VISIBLE else android.view.View.GONE
        commandsList.visibility = if (entries.isEmpty()) android.view.View.GONE else android.view.View.VISIBLE
    }
}
