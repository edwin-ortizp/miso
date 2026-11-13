package com.edwinortizp.lifetracker

import android.annotation.SuppressLint
import android.app.AlertDialog
import android.content.ActivityNotFoundException
import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import android.net.http.SslError
import android.os.Bundle
import android.webkit.*
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.viewinterop.AndroidView

class MainActivity : ComponentActivity() {
    private val policy = UrlPolicy(BuildConfig.MISO_BASE_URL)
    private lateinit var web: WebView
    private var loadProgress by mutableIntStateOf(0)
    private var failed by mutableStateOf(false)
    private var lastUrl = BuildConfig.MISO_BASE_URL
    private var fileCallback: ValueCallback<Array<Uri>>? = null
    private var pendingDownload: String? = null
    private val downloads: DownloadViewModel by viewModels()
    private lateinit var back: OnBackPressedCallback

    private val chooseFiles = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        val values = WebChromeClient.FileChooserParams.parseResult(result.resultCode, result.data)
        fileCallback?.onReceiveValue(values?.filter { it.scheme == "content" }?.toTypedArray())
        fileCallback = null
    }
    private val saveFile = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        val url = pendingDownload
        pendingDownload = null
        val target = result.data?.data
        if (result.resultCode == RESULT_OK && url != null && target != null) download(url, target)
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        pendingDownload = savedInstanceState?.getString("download")
        lastUrl = savedInstanceState?.getString("lastUrl")?.takeIf { policy.destination(it) == UrlPolicy.Destination.INTERNAL }
            ?: BuildConfig.MISO_BASE_URL
        web = WebView(this).apply {
            id = android.view.View.generateViewId()
            settings.javaScriptEnabled = true
            settings.domStorageEnabled = true
            settings.allowFileAccess = false
            settings.allowContentAccess = false
            settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
            settings.javaScriptCanOpenWindowsAutomatically = false
            settings.setSupportMultipleWindows(true)
            CookieManager.getInstance().setAcceptCookie(true)
            CookieManager.getInstance().setAcceptThirdPartyCookies(this, false)
            webViewClient = client()
            webChromeClient = chrome()
            setDownloadListener { url, _, disposition, mime, _ -> requestDownload(url, disposition, mime) }
        }
        WebView.setWebContentsDebuggingEnabled(BuildConfig.DEBUG)
        back = object : OnBackPressedCallback(false) {
            override fun handleOnBackPressed() { failed = false; web.goBack() }
        }
        onBackPressedDispatcher.addCallback(this, back)
        setContent {
            MaterialTheme(colorScheme = lightColorScheme(primary = Color(0xFF2B5BB5))) {
                LaunchedEffect(downloads.result) {
                    downloads.result?.let { toast(it); downloads.result = null }
                }
                Box(Modifier.fillMaxSize().background(Color(0xFFFBF8FD)).safeDrawingPadding().imePadding()) {
                    AndroidView(factory = { web }, modifier = Modifier.fillMaxSize())
                    if (loadProgress < 100 && !failed) {
                        val loadingLabel = stringResource(R.string.loading)
                        LinearProgressIndicator(progress = { loadProgress / 100f }, modifier = Modifier.fillMaxWidth().semantics { contentDescription = loadingLabel })
                    }
                    if (failed) {
                        Column(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.surface).padding(24.dp),
                            horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
                            Text(stringResource(R.string.load_error), style = MaterialTheme.typography.titleLarge)
                            Spacer(Modifier.height(12.dp))
                            Text(stringResource(R.string.load_help))
                            Spacer(Modifier.height(20.dp))
                            Button(onClick = { failed = false; web.loadUrl(lastUrl) }) { Text(stringResource(R.string.retry)) }
                        }
                    }
                }
            }
        }
        val restored = savedInstanceState?.getBundle("web")?.let { web.restoreState(it) }
        if (restored == null) web.loadUrl(lastUrl)
        back.isEnabled = web.canGoBack()
    }

    private fun client() = object : WebViewClient() {
        override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
            val url = request.url.toString()
            if (policy.destination(url) == UrlPolicy.Destination.INTERNAL) return false
            if (request.isForMainFrame) openExternal(url)
            return true
        }
        override fun onPageStarted(view: WebView, url: String, favicon: Bitmap?) {
            if (policy.destination(url) != UrlPolicy.Destination.INTERNAL) {
                view.stopLoading()
                failed = true
                return
            }
            lastUrl = url
            loadProgress = 0
            failed = false
        }
        override fun onPageFinished(view: WebView, url: String) {
            loadProgress = 100
            CookieManager.getInstance().flush()
            back.isEnabled = view.canGoBack()
        }
        override fun doUpdateVisitedHistory(view: WebView, url: String, isReload: Boolean) {
            back.isEnabled = view.canGoBack()
        }
        override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
            if (request.isForMainFrame) { failed = true; loadProgress = 100 }
        }
        override fun onReceivedHttpError(view: WebView, request: WebResourceRequest, response: WebResourceResponse) {
            if (request.isForMainFrame) { failed = true; loadProgress = 100 }
        }
        override fun onReceivedSslError(view: WebView, handler: SslErrorHandler, error: SslError) {
            handler.cancel()
            if (error.url == lastUrl) failed = true
        }
    }

    private fun chrome() = object : WebChromeClient() {
        override fun onProgressChanged(view: WebView, newProgress: Int) { loadProgress = newProgress }
        override fun onShowFileChooser(view: WebView, callback: ValueCallback<Array<Uri>>, params: FileChooserParams): Boolean {
            fileCallback?.onReceiveValue(null)
            if (policy.destination(view.url.orEmpty()) != UrlPolicy.Destination.INTERNAL) { callback.onReceiveValue(null); return true }
            fileCallback = callback
            val types = params.acceptTypes.filter { it.contains('/') }.toTypedArray()
            val intent = Intent(Intent.ACTION_GET_CONTENT).apply {
                addCategory(Intent.CATEGORY_OPENABLE)
                type = if (types.size == 1) types[0] else "*/*"
                if (types.size > 1) putExtra(Intent.EXTRA_MIME_TYPES, types)
                putExtra(Intent.EXTRA_ALLOW_MULTIPLE, params.mode == FileChooserParams.MODE_OPEN_MULTIPLE)
            }
            try { chooseFiles.launch(intent) } catch (_: ActivityNotFoundException) {
                callback.onReceiveValue(null); fileCallback = null; toast(R.string.link_unavailable)
            }
            return true
        }
        // target=_blank/window.open must pass through the same origin policy, too.
        override fun onCreateWindow(view: WebView, dialog: Boolean, gesture: Boolean, message: android.os.Message): Boolean {
            if (!gesture) return false
            val popup = WebView(this@MainActivity)
            popup.webViewClient = object : WebViewClient() {
                override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                    val url = request.url.toString()
                    if (policy.destination(url) == UrlPolicy.Destination.INTERNAL) web.loadUrl(url) else openExternal(url)
                    view.destroy()
                    return true
                }
            }
            (message.obj as WebView.WebViewTransport).webView = popup
            message.sendToTarget()
            return true
        }
        override fun onJsAlert(view: WebView, url: String, message: String, result: JsResult): Boolean {
            AlertDialog.Builder(this@MainActivity).setTitle(R.string.app_name).setMessage(message)
                .setPositiveButton(R.string.accept) { _, _ -> result.confirm() }
                .setOnCancelListener { result.cancel() }.show()
            return true
        }
        override fun onJsConfirm(view: WebView, url: String, message: String, result: JsResult): Boolean {
            AlertDialog.Builder(this@MainActivity).setTitle(R.string.app_name).setMessage(message)
                .setPositiveButton(R.string.accept) { _, _ -> result.confirm() }
                .setNegativeButton(R.string.cancel) { _, _ -> result.cancel() }
                .setOnCancelListener { result.cancel() }.show()
            return true
        }
    }

    private fun openExternal(url: String) {
        val action = when (policy.destination(url)) {
            UrlPolicy.Destination.EXTERNAL -> Intent.ACTION_VIEW
            UrlPolicy.Destination.EMAIL -> Intent.ACTION_SENDTO
            UrlPolicy.Destination.PHONE -> Intent.ACTION_DIAL
            else -> { toast(R.string.link_unavailable); return }
        }
        try { startActivity(Intent(action, Uri.parse(url)).addCategory(Intent.CATEGORY_BROWSABLE)) }
        catch (_: ActivityNotFoundException) { toast(R.string.link_unavailable) }
    }

    private fun requestDownload(url: String, disposition: String?, mime: String?) {
        if (policy.destination(url) != UrlPolicy.Destination.INTERNAL) { toast(R.string.link_unavailable); return }
        if (downloads.busy || pendingDownload != null) { toast(R.string.download_busy); return }
        pendingDownload = url
        val filename = URLUtil.guessFileName(url, disposition, mime).substringAfterLast('/').substringAfterLast('\\')
        val intent = Intent(Intent.ACTION_CREATE_DOCUMENT).apply {
            addCategory(Intent.CATEGORY_OPENABLE)
            type = mime?.takeIf { it.contains('/') } ?: "application/octet-stream"
            putExtra(Intent.EXTRA_TITLE, filename)
            putExtra(Intent.EXTRA_LOCAL_ONLY, true)
        }
        try { saveFile.launch(Intent.createChooser(intent, getString(R.string.save_file))) }
        catch (_: ActivityNotFoundException) { pendingDownload = null; toast(R.string.download_failed) }
    }

    private fun download(url: String, target: Uri) {
        val cookie = CookieManager.getInstance().getCookie(url)
        val agent = web.settings.userAgentString
        downloads.save(url, cookie, agent, target)
    }

    private fun toast(message: Int) = Toast.makeText(this, message, Toast.LENGTH_LONG).show()
    override fun onSaveInstanceState(outState: Bundle) {
        outState.putBundle("web", Bundle().also { web.saveState(it) })
        outState.putString("lastUrl", lastUrl)
        outState.putString("download", pendingDownload)
        super.onSaveInstanceState(outState)
    }
    override fun onPause() { CookieManager.getInstance().flush(); web.onPause(); super.onPause() }
    override fun onResume() { super.onResume(); if (::web.isInitialized) web.onResume() }
    override fun onDestroy() {
        fileCallback?.onReceiveValue(null)
        fileCallback = null
        (web.parent as? android.view.ViewGroup)?.removeView(web)
        web.destroy()
        super.onDestroy()
    }
}
