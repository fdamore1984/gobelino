package com.gobelino.agent.util

import android.content.Context
import android.os.Build
import android.util.Log
import java.io.File
import java.io.PrintWriter
import java.io.StringWriter
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Registra i crash su un file in storage esterno app-specific
 * (Android/data/com.gobelino.agent/files/crash_log.txt), leggibile con
 * un qualsiasi file manager o via cavo USB (MTP) senza bisogno di adb
 * né root — utile perché su questi dispositivi arruolati come Device
 * Owner spesso il debug USB non è disponibile o è disabilitato da
 * policy, quindi il logcat classico non è recuperabile sul campo.
 *
 * Il file viene troncato oltre una dimensione ragionevole per non
 * accumulare indefinitamente.
 */
object CrashLogger {

    private const val TAG = "CrashLogger"
    private const val FILE_NAME = "crash_log.txt"
    private const val MAX_SIZE_BYTES = 200 * 1024 // 200 KB

    /** Registra un'eccezione gestita (non ha fatto crashare il processo). */
    fun record(context: Context, source: String, throwable: Throwable) {
        write(context, source, throwable, fatal = false)
    }

    /**
     * Installa un handler globale per le eccezioni non gestite: le
     * registra su file e poi delega sempre all'handler precedente, in
     * modo da non alterare il comportamento di default del sistema
     * (l'app termina comunque) — serve solo ad avere una traccia
     * persistente di COSA è crashato, dato che il logcat sul campo
     * spesso non è raggiungibile.
     */
    fun installGlobalHandler(context: Context) {
        val previous = Thread.getDefaultUncaughtExceptionHandler()
        Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->
            try {
                write(context.applicationContext, "uncaught:${thread.name}", throwable, fatal = true)
            } catch (loggingError: Throwable) {
                Log.e(TAG, "failed to persist crash log", loggingError)
            }
            previous?.uncaughtException(thread, throwable)
        }
    }

    /** L'ultimo crash registrato, se presente, per una rapida ispezione in-app. */
    fun lastEntry(context: Context): String? {
        val file = logFile(context) ?: return null
        if (!file.exists()) return null
        return runCatching { file.readText() }.getOrNull()?.substringAfterLast("\n----\n")
    }

    private fun write(context: Context, source: String, throwable: Throwable, fatal: Boolean) {
        val file = logFile(context) ?: return
        if (file.exists() && file.length() > MAX_SIZE_BYTES) {
            file.delete()
        }

        val timestamp = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())
        val sw = StringWriter()
        throwable.printStackTrace(PrintWriter(sw))

        val entry = buildString {
            append("----\n")
            append("$timestamp | fatal=$fatal | source=$source\n")
            append("device: ${Build.MANUFACTURER} ${Build.MODEL}, Android ${Build.VERSION.RELEASE} (API ${Build.VERSION.SDK_INT})\n")
            append(sw.toString())
        }

        runCatching {
            file.appendText(entry)
        }.onFailure {
            Log.e(TAG, "failed to write crash log", it)
        }
    }

    private fun logFile(context: Context): File? {
        val dir = context.getExternalFilesDir(null) ?: context.filesDir
        return File(dir, FILE_NAME)
    }
}
