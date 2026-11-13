package com.edwinortizp.lifetracker

import org.junit.Assert.*
import org.junit.Test
import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream
import java.net.HttpURLConnection
import java.net.URL

class AuthenticatedDownloadTest {
    private val base = "https://miso.novapixel.org/"
    private class Response(private val status: Int, private val location: String? = null, private val mime: String = "application/pdf") : HttpURLConnection(URL("https://miso.novapixel.org/file")) {
        var closed = false
        override fun getResponseCode() = status
        override fun getHeaderField(name: String): String? = if (name == "Location") location else null
        override fun getContentType() = mime
        override fun getInputStream() = ByteArrayInputStream("document".toByteArray())
        override fun disconnect() { closed = true }
        override fun usingProxy() = false
        override fun connect() {}
    }
    @Test fun downloadCopiesBytesAndSuppliesSession() {
        val response = Response(200)
        val output = ByteArrayOutputStream()
        AuthenticatedDownload(UrlPolicy(base)) { response }.copy(base + "file", "session=test", "MiSo") { output }
        assertEquals("document", output.toString())
        assertEquals("session=test", response.getRequestProperty("Cookie"))
        assertFalse(response.instanceFollowRedirects)
        assertTrue(response.closed)
    }
    @Test fun externalRedirectNeverReceivesCookies() {
        val response = Response(302, "https://evil.test/steal")
        var connections = 0
        var outputOpened = false
        val downloader = AuthenticatedDownload(UrlPolicy(base)) { connections++; response }
        assertThrows(IllegalArgumentException::class.java) {
            downloader.copy(base + "file", "session=test", "MiSo") { outputOpened = true; ByteArrayOutputStream() }
        }
        assertEquals(1, connections)
        assertFalse(outputOpened)
        assertTrue(response.closed)
    }
    @Test fun sameOriginRedirectIsSupported() {
        val redirect = Response(302, "/attachment")
        val file = Response(200)
        val urls = mutableListOf<String>()
        AuthenticatedDownload(UrlPolicy(base)) { url -> urls.add(url); if (urls.size == 1) redirect else file }
            .copy(base + "file", null, "MiSo") { ByteArrayOutputStream() }
        assertEquals(listOf(base + "file", base + "attachment"), urls)
    }
    @Test fun loginHtmlAndHttpErrorsAreNotSaved() {
        listOf(Response(200, mime = "text/html; charset=UTF-8"), Response(401), Response(500)).forEach { response ->
            var outputOpened = false
            assertThrows(IllegalStateException::class.java) {
                AuthenticatedDownload(UrlPolicy(base)) { response }.copy(base + "file", null, "MiSo") {
                    outputOpened = true; ByteArrayOutputStream()
                }
            }
            assertFalse(outputOpened)
            assertTrue(response.closed)
        }
    }
}
