package com.edwinortizp.lifetracker

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class MisoMessagingService : FirebaseMessagingService() {
    override fun onNewToken(token: String) { PushState(this).token(token) }
    override fun onMessageReceived(message: RemoteMessage) { NativeNotifications.show(this,message.data) }
}
