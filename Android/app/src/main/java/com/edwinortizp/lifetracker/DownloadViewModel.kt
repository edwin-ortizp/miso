package com.edwinortizp.lifetracker

import android.app.Application
import android.net.Uri
import android.provider.DocumentsContract
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/** Retains a running save across activity recreation without retaining an Activity. */
class DownloadViewModel(application: Application) : AndroidViewModel(application) {
    var busy by mutableStateOf(false)
        private set
    var result by mutableStateOf<Int?>(null)

    fun save(url: String, cookie: String?, agent: String, target: Uri) {
        if (busy) return
        busy = true
        val resolver = getApplication<Application>().contentResolver
        viewModelScope.launch {
            val success = withContext(Dispatchers.IO) {
                try {
                    AuthenticatedDownload(UrlPolicy(BuildConfig.MISO_BASE_URL)).save(url, cookie, agent, target, resolver)
                    true
                } catch (_: Exception) {
                    runCatching { DocumentsContract.deleteDocument(resolver, target) }
                    false
                }
            }
            busy = false
            result = if (success) R.string.download_saved else R.string.download_failed
        }
    }
}
