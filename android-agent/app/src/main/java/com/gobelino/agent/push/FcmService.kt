package com.gobelino.agent.push

import android.util.Log
import com.gobelino.agent.util.Prefs
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class FcmService : FirebaseMessagingService() {

    companion object {
        private const val TAG = "FcmService"
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.i(TAG, "Nuovo token FCM ricevuto")
        Prefs.of(applicationContext).fcmToken = token
        // TODO (step successivo): invio del token al backend
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        Log.i(TAG, "Messaggio FCM ricevuto: data=${message.data}")
        // TODO (step successivo): se type == "poll_now", forzare poll immediato
    }
}
