package com.edwinortizp.lifetracker

import android.content.Context
import java.security.SecureRandom
import java.util.UUID

class PushState(context: Context) {
    private val prefs = context.getSharedPreferences("miso-native", Context.MODE_PRIVATE)
    val installationId: String get() = value("id")
    val secret: String get() = value("secret")
    val binding: String get() = value("binding")
    val account: String get() = value("account")
    val enabled: Boolean get() = prefs.getBoolean("enabled", false)
    val token: String get() = value("token")
    val tokenDirty: Boolean get() = prefs.getBoolean("token_dirty", false)
    init {
        if (value("id").isEmpty()) {
            val bytes = ByteArray(32).also { SecureRandom().nextBytes(it) }
            prefs.edit().putString("id", UUID.randomUUID().toString()).putString("secret", bytes.joinToString("") { "%02x".format(it) }).commit()
        }
    }
    private fun value(key: String) = prefs.getString(key, "").orEmpty()
    fun token(token: String) { if (this.token != token) prefs.edit().putString("token", token).putBoolean("token_dirty",true).apply() }
    fun session(account: String) { if (this.account != account) clear(); prefs.edit().putString("account", account).commit() }
    fun bind(binding: String) { prefs.edit().putString("binding", binding).putBoolean("enabled", true).putBoolean("token_dirty",false).commit() }
    fun clear() { prefs.edit().remove("binding").putBoolean("enabled", false).commit() }
}
