package com.gobelino.agent.push

import android.util.Log
import com.gobelino.agent.net.ApiClient
import com.gobelino.agent.util.Prefs
import com.gobelino.agent.worker.PollScheduler
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class FcmService : FirebaseMessagingService() {

    companion object {
        private const val TAG = "FcmService"
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.i(TAG, "Nuovo token FCM ricevuto")

        val prefs = Prefs.of(applicationContext)
        prefs.fcmToken = token

        val serverUrl = prefs.serverUrl
        val deviceToken = prefs.deviceToken

        if (serverUrl == null || deviceToken == null) {
            return
        }

        CoroutineScope(Dispatchers.IO).launch {
            try {
                ApiClient(serverUrl).updateFcmToken(deviceToken, token)
            } catch (e: Exception) {
                Log.w(TAG, "Invio token FCM fallito, verra' ritentato piu' avanti", e)
            }
        }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        Log.i(TAG, "Messaggio FCM ricevuto: data=${message.data}")

        if (message.data["type"] == "poll_now") {
            CoroutineScope(Dispatchers.IO).launch {
                PollScheduler.forceNow(applicationContext)
            }
        }
    }
}