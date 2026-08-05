package com.gobelino.agent.worker

import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageInstaller
import android.os.Build
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.File
import java.io.IOException
import java.util.concurrent.TimeUnit
import kotlin.coroutines.resume

/**
 * Downloads an APK from a public URL and installs it silently via
 * PackageInstaller. No user interaction happens on the device: this
 * only works because the agent is Device Owner, which lets it commit
 * an install session without the usual "install unknown app" prompt.
 *
 * Triggered by the "install_app" command (payload: {"apk_url": "..."}),
 * queued from the panel's device actions menu.
 */
object ApkInstaller {

    private const val ACTION_INSTALL_RESULT = "com.gobelino.agent.action.INSTALL_RESULT"

    // Sanity cap so a misconfigured/malicious URL can't fill the
    // device's storage via an unbounded download.
    private const val MAX_APK_BYTES = 300L * 1024 * 1024
    private val INSTALL_TIMEOUT_MILLIS = TimeUnit.MINUTES.toMillis(3)

    private val http = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(120, TimeUnit.SECONDS)
        .build()

    /** Downloads [apkUrl] and installs it. Throws on any failure. */
    suspend fun installFromUrl(context: Context, apkUrl: String) {
        val url = apkUrl.toHttpUrlOrNull() ?: throw IllegalArgumentException("invalid_apk_url")
        require(url.scheme == "https") { "apk_url must use https" }

        val apkFile = download(context, url.toString())
        try {
            installSilently(context, apkFile)
        } finally {
            apkFile.delete()
        }
    }

    private fun download(context: Context, apkUrl: String): File {
        val outFile = File(context.cacheDir, "agent-install-${System.currentTimeMillis()}.apk")
        val request = Request.Builder().url(apkUrl).build()

        http.newCall(request).execute().use { response ->
            if (!response.isSuccessful) throw IOException("download_failed_http_${response.code}")
            val body = response.body ?: throw IOException("empty_response_body")

            body.byteStream().use { input ->
                outFile.outputStream().use { output ->
                    val buffer = ByteArray(64 * 1024)
                    var total = 0L
                    while (true) {
                        val read = input.read(buffer)
                        if (read == -1) break
                        total += read
                        if (total > MAX_APK_BYTES) throw IOException("apk_too_large")
                        output.write(buffer, 0, read)
                    }
                }
            }
        }

        if (outFile.length() == 0L) throw IOException("downloaded_apk_empty")
        return outFile
    }

    private suspend fun installSilently(context: Context, apkFile: File) {
        val packageInstaller = context.packageManager.packageInstaller
        val params = PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            // Redundant for a Device Owner (already silent), but makes
            // the intent explicit and future-proofs against OS changes.
            params.setRequireUserAction(PackageInstaller.SessionParams.USER_ACTION_NOT_REQUIRED)
        }

        val sessionId = packageInstaller.createSession(params)
        val session = packageInstaller.openSession(sessionId)

        try {
            session.openWrite("agent_apk", 0, apkFile.length()).use { out ->
                apkFile.inputStream().use { input -> input.copyTo(out) }
                session.fsync(out)
            }

            val (status, message) = awaitInstallResult(context, sessionId) { intentSender ->
                session.commit(intentSender)
            }

            if (status != PackageInstaller.STATUS_SUCCESS) {
                throw IOException("install_failed_status_${status}_${message ?: "no_message"}")
            }
        } finally {
            try {
                session.close()
            } catch (e: Exception) {
                // Session may already be closed/abandoned by a
                // successful commit(); never let cleanup mask the
                // real install result above.
            }
        }
    }

    /**
     * commit() reports its outcome asynchronously via a broadcast to
     * the PendingIntent we hand it, so we bridge that back into this
     * suspend call with a short-lived dynamic receiver. Bounded by
     * [INSTALL_TIMEOUT_MILLIS] in case the broadcast never arrives.
     */
    private suspend fun awaitInstallResult(
        context: Context,
        sessionId: Int,
        commit: (android.content.IntentSender) -> Unit,
    ): Pair<Int, String?> {
        val appContext = context.applicationContext

        val result = withTimeoutOrNull(INSTALL_TIMEOUT_MILLIS) {
            suspendCancellableCoroutine<Pair<Int, String?>> { cont ->
                val receiver = object : BroadcastReceiver() {
                    override fun onReceive(receiverContext: Context, intent: Intent) {
                        if (intent.getIntExtra(PackageInstaller.EXTRA_SESSION_ID, -1) != sessionId) return

                        try {
                            appContext.unregisterReceiver(this)
                        } catch (e: Exception) {
                            // Already unregistered (e.g. by cancellation below).
                        }

                        val status = intent.getIntExtra(
                            PackageInstaller.EXTRA_STATUS,
                            PackageInstaller.STATUS_FAILURE,
                        )
                        val message = intent.getStringExtra(PackageInstaller.EXTRA_STATUS_MESSAGE)

                        if (cont.isActive) cont.resume(status to message)
                    }
                }

                val filter = IntentFilter(ACTION_INSTALL_RESULT)
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                    appContext.registerReceiver(receiver, filter, Context.RECEIVER_NOT_EXPORTED)
                } else {
                    @Suppress("DEPRECATION")
                    appContext.registerReceiver(receiver, filter)
                }

                cont.invokeOnCancellation {
                    try {
                        appContext.unregisterReceiver(receiver)
                    } catch (e: Exception) {
                        // No-op: never received, or already unregistered above.
                    }
                }

                val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE
                } else {
                    PendingIntent.FLAG_UPDATE_CURRENT
                }
                val pendingIntent = PendingIntent.getBroadcast(
                    appContext,
                    sessionId,
                    Intent(ACTION_INSTALL_RESULT).setPackage(appContext.packageName),
                    flags,
                )

                commit(pendingIntent.intentSender)
            }
        }

        return result ?: (PackageInstaller.STATUS_FAILURE to "timed_out_waiting_for_install_result")
    }
}
