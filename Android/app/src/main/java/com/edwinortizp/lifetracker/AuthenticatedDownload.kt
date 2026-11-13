package com.edwinortizp.lifetracker

import android.content.ContentResolver
import android.net.Uri
import java.net.HttpURLConnection
import java.net.URI
import java.net.URL
import java.io.OutputStream

/** Cookies and redirects are restricted to the configured MiSo origin. */
class AuthenticatedDownload(
    private val policy: UrlPolicy,
    private val connect: (String) -> HttpURLConnection = { URL(it).openConnection() as HttpURLConnection },
) {
    fun save(url: String, cookie: String?, userAgent: String, target: Uri, resolver: ContentResolver) {
        copy(url, cookie, userAgent) { requireNotNull(resolver.openOutputStream(target, "wt")) }
    }

    internal fun copy(url: String, cookie: String?, userAgent: String, output: () -> OutputStream) {
        var current = url
        repeat(6) {
            require(policy.destination(current) == UrlPolicy.Destination.INTERNAL)
            val connection = connect(current)
            connection.instanceFollowRedirects = false
            connection.connectTimeout = 20_000
            connection.readTimeout = 30_000
            connection.setRequestProperty("User-Agent", userAgent)
            if (!cookie.isNullOrBlank()) connection.setRequestProperty("Cookie", cookie)
            try {
                val status = connection.responseCode
                if (status in listOf(301, 302, 303, 307, 308)) {
                    current = URI(current).resolve(connection.getHeaderField("Location") ?: error("Missing redirect")).toString()
                } else {
                    check(status in 200..299) { "HTTP $status" }
                    // Never save a redirected login form as if it were the requested attachment.
                    check(!connection.contentType.orEmpty().startsWith("text/html", true))
                    connection.inputStream.use { input ->
                        output().use { destination -> input.copyTo(destination) }
                    }
                    return
                }
            } finally { connection.disconnect() }
        }
        error("Too many redirects")
    }
}
