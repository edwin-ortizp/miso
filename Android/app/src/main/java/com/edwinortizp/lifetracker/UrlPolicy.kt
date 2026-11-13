package com.edwinortizp.lifetracker

import java.net.URI

class UrlPolicy(baseUrl: String) {
    enum class Destination { INTERNAL, EXTERNAL, EMAIL, PHONE, BLOCKED }
    private val origin = URI(baseUrl)
    init { require(origin.scheme == "https" && origin.host != null && origin.rawUserInfo == null) }

    fun destination(value: String): Destination = try {
        val uri = URI(value)
        when {
            uri.rawUserInfo != null -> Destination.BLOCKED
            uri.scheme.equals("https", true) && uri.host != null ->
                if (uri.host.equals(origin.host, true) && port(uri) == port(origin)) Destination.INTERNAL else Destination.EXTERNAL
            uri.scheme.equals("mailto", true) && !uri.rawSchemeSpecificPart.isNullOrBlank() -> Destination.EMAIL
            uri.scheme.equals("tel", true) && !uri.rawSchemeSpecificPart.isNullOrBlank() -> Destination.PHONE
            else -> Destination.BLOCKED
        }
    } catch (_: Exception) { Destination.BLOCKED }

    private fun port(uri: URI) = if (uri.port == -1) 443 else uri.port
}
