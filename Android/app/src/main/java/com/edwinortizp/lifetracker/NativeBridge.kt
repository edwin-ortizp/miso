package com.edwinortizp.lifetracker

import android.content.Intent
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import android.webkit.WebView
import androidx.webkit.WebViewCompat
import androidx.webkit.WebViewFeature
import androidx.webkit.JavaScriptReplyProxy
import com.google.firebase.messaging.FirebaseMessaging
import org.json.JSONObject
import java.lang.ref.WeakReference
import java.net.URI
import java.util.UUID

class NativeBridge(private val activity: MainActivity, private val web: WebView, private val askPermission: ((Boolean) -> Unit) -> Unit) {
    companion object {
        private var reference = WeakReference<NativeBridge>(null)
        val active get() = reference.get()
    }
    var ready = false
        private set
    private val state = PushState(activity)
    private val handler = Handler(Looper.getMainLooper())
    private var backId: String? = null
    private var backTimeout: Runnable? = null
    private var pendingNotice: Pair<String,String>? = null
    fun install() {
        if (!WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER)) return
        val uri = URI(BuildConfig.MISO_BASE_URL)
        val origin = "${uri.scheme}://${uri.authority}"
        WebViewCompat.addWebMessageListener(web,"MisoNative",setOf(origin)) { _, message, source, main, reply ->
            if (!main || UrlPolicy(BuildConfig.MISO_BASE_URL).destination(source.toString()) != UrlPolicy.Destination.INTERNAL) return@addWebMessageListener
            val raw = runCatching { message.data }.getOrNull() ?: return@addWebMessageListener
            if (raw.length > 8192) return@addWebMessageListener
            val data = runCatching { JSONObject(raw) }.getOrNull() ?: return@addWebMessageListener
            if (data.optInt("v") != 1) return@addWebMessageListener
            val id = data.optString("id").take(64)
            when (data.optString("type")) {
                "navigation" -> { ready = true; activity.updateBack(); openPendingNotice() }
                "backResult" -> if (id == backId) {
                    backId = null; backTimeout?.let(handler::removeCallbacks)
                    if (!data.optBoolean("handled",true)) activity.performWebBack()
                }
                "session" -> {
                    val account = data.optString("account").takeIf { it.matches(Regex("[a-f0-9]{64}")) }.orEmpty()
                    if (state.account != account || account.isEmpty()) { state.clear(); NativeNotifications.clear(activity) }
                    state.session(account)
                    if (account.isNotEmpty()) openPendingNotice()
                }
                "pushStatus" -> respond(reply,id,NativeNotifications.status(activity))
                "pushEnable" -> {
                    if (state.account.isEmpty() || !BuildConfig.FIREBASE_CONFIGURED) respond(reply,id,"unconfigured")
                    else askPermission { granted ->
                        if (!granted) respond(reply,id,"denied")
                        else {
                            FirebaseMessaging.getInstance().isAutoInitEnabled = true
                            FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                                if (task.isSuccessful) { state.token(task.result); respond(reply,id,NativeNotifications.status(activity)) }
                                else respond(reply,id,"unsupported")
                            }
                        }
                    }
                }
                "pushBind" -> {
                    val binding = data.optString("binding")
                    if (state.account.isNotEmpty() && runCatching { UUID.fromString(binding) }.isSuccess) state.bind(binding)
                    respond(reply,id,NativeNotifications.status(activity))
                }
                "pushDisable" -> {
                    state.clear(); NativeNotifications.clear(activity)
                    if (BuildConfig.FIREBASE_CONFIGURED) FirebaseMessaging.getInstance().isAutoInitEnabled = false
                    respond(reply,id,"off")
                }
                "pushSettings" -> {
                    activity.startActivity(Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE,activity.packageName))
                    respond(reply,id,NativeNotifications.status(activity))
                }
            }
        }
        reference = WeakReference(this)
    }
    private fun respond(reply: JavaScriptReplyProxy, id: String, status: String) {
        val data = JSONObject().put("v",1).put("id",id).put("state",status).put("enabled",state.enabled)
            .put("binding",state.binding).put("token_dirty",state.tokenDirty)
            .put("installation_id",state.installationId).put("secret",state.secret).put("token",state.token)
        runCatching { reply.postMessage(data.toString()) }
    }
    fun emit(data: JSONObject) { handler.post { if (!activity.isDestroyed) web.evaluateJavascript("window.MisoAndroid?.receive(${data});",null) } }
    fun back() {
        if (backId != null) return
        backId = UUID.randomUUID().toString()
        web.evaluateJavascript("window.MisoAndroid?.back(${JSONObject.quote(backId)});",null)
        backTimeout = Runnable {
            if (backId != null) { backId = null; android.widget.Toast.makeText(activity,"No pudimos completar la acción. Inténtalo de nuevo.",android.widget.Toast.LENGTH_LONG).show() }
        }.also { handler.postDelayed(it, 20000) }
    }
    fun pageStarted() { ready = false; backId = null; backTimeout?.let(handler::removeCallbacks) }
    fun notice(intent: Intent?) {
        val id = intent?.getStringExtra("notice_id") ?: return
        val binding = intent.getStringExtra("notice_binding").orEmpty()
        if (!state.enabled || state.binding != binding || runCatching { UUID.fromString(id) }.isFailure) return
        pendingNotice = id to binding
        openPendingNotice()
    }
    fun initialNoticeUrl(intent: Intent?): String? {
        val id = intent?.getStringExtra("notice_id") ?: return null
        if (!state.enabled || state.binding != intent.getStringExtra("notice_binding") || runCatching { UUID.fromString(id) }.isFailure) return null
        return URI(BuildConfig.MISO_BASE_URL).resolve("android/notifications/$id").toString()
    }
    private fun openPendingNotice() {
        val notice = pendingNotice ?: return
        if (ready && state.account.isNotEmpty() && state.binding == notice.second) {
            web.evaluateJavascript("window.MisoAndroid?.openNotice(${JSONObject.quote(notice.first)});",null)
            pendingNotice = null
        }
    }
    fun resume() { if (ready) emit(JSONObject().put("v",1).put("type","resume")) }
    fun destroy() { handler.removeCallbacksAndMessages(null); if (active === this) reference.clear() }
}
