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
import com.journeyapps.barcodescanner.CaptureActivity
import com.google.zxing.integration.android.IntentIntegrator
import com.google.zxing.integration.android.IntentResult
import org.json.JSONObject
import android.app.Activity
import android.content.ComponentName
import android.content.pm.PackageManager

/**
 * Launcher activity. In normal operation the device just sits on
 * this screen (or, in kiosk mode, is pinned to it via LockTask) while
 * PollWorker does all the real work in the background. It also shows
 * the log of commands received from the server and lets the user
 * force an immediate check-in instead of waiting for the next poll.
 */
class MainActivity : AppCompatActivity() {

    companion object {
        private const val REQUEST_PROVISION_MANAGED_PROFILE = 2001
    }

    private lateinit var statusText: TextView
    private lateinit var forceSyncButton: Button
    private lateinit var commandsEmptyText: TextView
    private lateinit var commandsList: ListView
    private lateinit var commandsAdapter: ArrayAdapter<String>
    private lateinit var workProfileButton: Button
    private lateinit var workProfileConfigLayout: android.widget.LinearLayout
    private lateinit var serverUrlInput: android.widget.EditText
    private lateinit var enrollmentTokenInput: android.widget.EditText
    private lateinit var saveConfigButton: Button
    private lateinit var scanQrButton: Button

    private var pendingSyncId: UUID? = null
    private val prefsListener =
    android.content.SharedPreferences.OnSharedPreferenceChangeListener { _, key ->
        if (key == "device_token") {
            runOnUiThread {
                updateStatus()
                updateWorkProfileUiState()
            }
        }
    }
    private val syncObserver = object : Observer<WorkInfo?> {
        override fun onChanged(value: WorkInfo?) {
            val info = value ?: return
            
            if (info.state.isFinished) {
                forceSyncButton.isEnabled = true
                forceSyncButton.setText(R.string.action_force_sync)
                Toast.makeText(
                    this@MainActivity, // <-- NOTA: Ora serve this@MainActivity per il Context
                    if (info.state == WorkInfo.State.SUCCEEDED) R.string.sync_success else R.string.sync_failed,
                    Toast.LENGTH_SHORT
                ).show()
                updateStatus()
                refreshCommandsList()
                
                // Usiamo 'this' invece di 'syncObserver' per rimuovere l'osservatore
                pendingSyncId?.let { 
                    WorkManager.getInstance(this@MainActivity).getWorkInfoByIdLiveData(it).removeObserver(this) 
                }
                pendingSyncId = null
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        statusText = findViewById(R.id.statusText)
        forceSyncButton = findViewById(R.id.forceSyncButton)
        workProfileButton = findViewById(R.id.workProfileButton)
        commandsEmptyText = findViewById(R.id.commandsEmptyText)
        commandsList = findViewById(R.id.commandsList)
        workProfileConfigLayout = findViewById(R.id.workProfileConfigLayout)
        serverUrlInput = findViewById(R.id.serverUrlInput)
        enrollmentTokenInput = findViewById(R.id.enrollmentTokenInput)
        saveConfigButton = findViewById(R.id.saveConfigButton)
        saveConfigButton.setOnClickListener { saveWorkProfileConfig() }
        scanQrButton = findViewById(R.id.scanQrButton)
        scanQrButton.setOnClickListener {
            IntentIntegrator(this)
                .setCaptureActivity(CaptureActivity::class.java)
                .setOrientationLocked(true)
                .setPrompt(getString(R.string.action_scan_qr))
                .initiateScan()
        }

        commandsAdapter = ArrayAdapter(this, android.R.layout.simple_list_item_1, mutableListOf())
        commandsList.adapter = commandsAdapter

        forceSyncButton.setOnClickListener { forceSync() }
        workProfileButton.setOnClickListener { startWorkProfileProvisioning() }
        updateWorkProfileUiState()

        ensurePollingScheduled()
        updateStatus()
        refreshCommandsList()

        Prefs.of(this).registerChangeListener(prefsListener)

    }

    override fun onDestroy() {
        super.onDestroy()
        Prefs.of(this).unregisterChangeListener(prefsListener)
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

    private fun updateWorkProfileButtonVisibility() {
        val dpm = getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        // Mostra il pulsante solo se il device non è già Device Owner
        // né esiste già un profilo gestito (Profile Owner) su questo device.
        val alreadyManaged = dpm.isDeviceOwnerApp(packageName) || dpm.isProfileOwnerApp(packageName)
        workProfileButton.visibility = if (alreadyManaged) android.view.View.GONE else android.view.View.VISIBLE
    }

    private fun startWorkProfileProvisioning() {
        val dpm = getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        if (!dpm.isProvisioningAllowed(DevicePolicyManager.ACTION_PROVISION_MANAGED_PROFILE)) {
            Toast.makeText(this, R.string.work_profile_unsupported, Toast.LENGTH_LONG).show()
            return
        }
        try {
            val intent = android.content.Intent(DevicePolicyManager.ACTION_PROVISION_MANAGED_PROFILE).apply {
                putExtra(
                    DevicePolicyManager.EXTRA_PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME,
                    com.gobelino.agent.receiver.AgentDeviceAdminReceiver.componentName(this@MainActivity)
                )
            }
            startActivityForResult(intent, REQUEST_PROVISION_MANAGED_PROFILE)
        } catch (e: android.content.ActivityNotFoundException) {
            Toast.makeText(this, R.string.work_profile_launch_failed, Toast.LENGTH_LONG).show()
        }
    }

    private fun updateWorkProfileUiState() {
        val dpm = getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        val isProfileOwner = dpm.isProfileOwnerApp(packageName)
        val isDeviceOwner = dpm.isDeviceOwnerApp(packageName)
        val notConfiguredYet = Prefs.of(this).serverUrl == null

        // Il bottone "Crea profilo di lavoro" sparisce se è già Device Owner
        // o se il Work Profile è già stato creato.
        workProfileButton.visibility =
            if (isDeviceOwner || isProfileOwner) android.view.View.GONE else android.view.View.VISIBLE

        // Il form di configurazione appare solo dopo la creazione del
        // Work Profile, finché server_url non è ancora stato salvato.
        workProfileConfigLayout.visibility =
            if (isProfileOwner && notConfiguredYet) android.view.View.VISIBLE else android.view.View.GONE
    }

    private fun saveWorkProfileConfig() {
        val url = serverUrlInput.text.toString().trim()
        val token = enrollmentTokenInput.text.toString().trim()

        if (url.isEmpty() || token.isEmpty()) {
            Toast.makeText(this, R.string.config_error_empty, Toast.LENGTH_SHORT).show()
            return
        }

        Prefs.of(this).apply {
            serverUrl = url
            pendingEnrollmentToken = token
        }

        ensurePollingScheduled()

        workProfileConfigLayout.visibility = android.view.View.GONE
        updateStatus() // mostra subito "status_enrolling"
        forceSync()    // il prefsListener sopra farà il resto quando device_token arriva
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: android.content.Intent?) {
        super.onActivityResult(requestCode, resultCode, data)

        if (requestCode == REQUEST_PROVISION_MANAGED_PROFILE) {
            if (resultCode == Activity.RESULT_OK) {
                // Provisioning riuscito: nasconde l'icona di questa app
                // nel LAUNCHER DEL PROFILO PERSONALE. La disabilitazione del
                // componente vale solo per l'utente/profilo che la esegue
                // (quello personale, dato che siamo ancora qui), quindi non
                // tocca la copia dell'app dentro il work profile.
                packageManager.setComponentEnabledSetting(
                    ComponentName(this, MainActivity::class.java),
                    PackageManager.COMPONENT_ENABLED_STATE_DISABLED,
                    PackageManager.DONT_KILL_APP
                )
            } else {
                Toast.makeText(this, R.string.work_profile_launch_failed, Toast.LENGTH_LONG).show()
            }
            return
        }

        // ramo già esistente per lo scan QR
        val result: IntentResult? = IntentIntegrator.parseActivityResult(requestCode, resultCode, data)
        val raw = result?.contents ?: return
        try {
            val json = JSONObject(raw)
            serverUrlInput.setText(json.getString("server_url"))
            enrollmentTokenInput.setText(json.getString("enrollment_token"))
        } catch (e: Exception) {
            Toast.makeText(this, R.string.config_scan_failed, Toast.LENGTH_SHORT).show()
        }
    }

}
