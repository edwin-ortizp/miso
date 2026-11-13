package com.edwinortizp.lifetracker

import android.app.Application
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.messaging.FirebaseMessaging

class MisoApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        if (BuildConfig.FIREBASE_CONFIGURED) {
            FirebaseApp.initializeApp(this, FirebaseOptions.Builder().setApplicationId(BuildConfig.FIREBASE_APP_ID)
                .setApiKey(BuildConfig.FIREBASE_API_KEY).setGcmSenderId(BuildConfig.FIREBASE_SENDER_ID)
                .setProjectId(BuildConfig.FIREBASE_PROJECT_ID).build())
            FirebaseMessaging.getInstance().isAutoInitEnabled = PushState(this).enabled
        }
        NativeNotifications.channel(this)
    }
}
