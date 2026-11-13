package com.edwinortizp.lifetracker

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import java.util.UUID

object NativeNotifications {
    const val CHANNEL = "miso-reminders"
    fun channel(context: Context) {
        context.getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel(CHANNEL, "Recordatorios de MiSo", NotificationManager.IMPORTANCE_DEFAULT))
    }
    fun status(context: Context): String {
        if (!BuildConfig.FIREBASE_CONFIGURED) return "unconfigured"
        if (com.google.android.gms.common.GoogleApiAvailabilityLight.getInstance().isGooglePlayServicesAvailable(context) != com.google.android.gms.common.ConnectionResult.SUCCESS) return "unsupported"
        if (Build.VERSION.SDK_INT >= 33 && ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) return "denied"
        if (!NotificationManagerCompat.from(context).areNotificationsEnabled() || context.getSystemService(NotificationManager::class.java).getNotificationChannel(CHANNEL)?.importance == NotificationManager.IMPORTANCE_NONE) return "blocked"
        return "ready"
    }
    fun eligible(enabled: Boolean, currentBinding: String, payload: Map<String,String>): Boolean = enabled && currentBinding.isNotEmpty() && payload["version"] == "1" && payload["binding"] == currentBinding && runCatching { UUID.fromString(payload["notice_id"]) }.isSuccess
    fun show(context: Context, payload: Map<String,String>) {
        val state = PushState(context)
        if (!eligible(state.enabled,state.binding,payload) || status(context) != "ready") return
        val id = payload.getValue("notice_id")
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP
            data = Uri.parse("miso-notice:$id")
            putExtra("notice_id", id); putExtra("notice_binding",state.binding)
        }
        val pending = PendingIntent.getActivity(context, 0, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val notification = NotificationCompat.Builder(context, CHANNEL).setSmallIcon(R.drawable.ic_notification)
            .setContentTitle("MiSo").setContentText("Tienes un recordatorio pendiente")
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE).setAutoCancel(true).setOnlyAlertOnce(true)
            .setContentIntent(pending).build()
        try { NotificationManagerCompat.from(context).notify(id,0,notification) } catch (_: SecurityException) { return }
        NativeBridge.active?.emit(org.json.JSONObject().put("v",1).put("type","notification"))
    }
    fun clear(context: Context) { NotificationManagerCompat.from(context).cancelAll() }
}
