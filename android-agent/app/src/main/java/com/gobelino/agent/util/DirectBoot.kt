package com.gobelino.agent.util

import android.content.Context
import android.os.Build

/**
 * Device-protected storage is readable/writable BEFORE the user
 * unlocks the device for the first time after a reboot — unlike the
 * default (credential-encrypted) storage, which simply doesn't exist
 * yet at that point. BootCompletedReceiver and PollForegroundService
 * now run pre-unlock (via LOCKED_BOOT_COMPLETED), so anything they
 * touch — device_token, queued command results/history — has to live
 * here, or it's invisible until someone physically unlocks the screen.
 */
fun Context.deviceProtected(): Context {
    val app = applicationContext
    return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
        app.createDeviceProtectedStorageContext()
    } else {
        app
    }
}

/**
 * One-time move of a SharedPreferences file from the default
 * credential-encrypted location into device-protected storage, so a
 * device enrolled before this change keeps its device_token / queued
 * commands instead of silently losing them. Cheap to call on every
 * startup: it's a no-op once the source file no longer exists there.
 */
fun Context.migrateToDeviceProtected(prefsName: String) {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
        deviceProtected().moveSharedPreferencesFrom(applicationContext, prefsName)
    }
}
