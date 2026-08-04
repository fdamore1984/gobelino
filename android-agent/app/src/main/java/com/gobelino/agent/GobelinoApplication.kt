package com.gobelino.agent

import android.app.Application
import com.gobelino.agent.util.CrashLogger

class GobelinoApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        // Installato il prima possibile: copre crash in qualunque
        // componente (activity di provisioning, receiver, worker),
        // non solo quelli intercettati localmente. Vedi CrashLogger
        // per il motivo (niente adb disponibile sul campo).
        CrashLogger.installGlobalHandler(this)
    }
}
