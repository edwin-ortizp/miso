package com.edwinortizp.lifetracker

import android.view.View
import android.view.ViewGroup
import android.view.MotionEvent
import android.os.SystemClock
import android.webkit.WebView
import android.webkit.WebSettings
import androidx.test.core.app.ActivityScenario
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

@RunWith(AndroidJUnit4::class)
class ShellInstrumentedTest {
    private fun web(view: View): WebView? {
        if (view is WebView) return view
        if (view is ViewGroup) for (i in 0 until view.childCount) web(view.getChildAt(i))?.let { return it }
        return null
    }
    private fun evaluate(scenario: ActivityScenario<MainActivity>, script: String): String {
        val latch = CountDownLatch(1)
        var value = ""
        scenario.onActivity { activity ->
            requireNotNull(web(activity.window.decorView)).evaluateJavascript(script) { value = it; latch.countDown() }
        }
        assertTrue("JavaScript callback", latch.await(5, TimeUnit.SECONDS))
        return value
    }
    private fun awaitCondition(condition: () -> Boolean) {
        val deadline = System.currentTimeMillis() + 30_000
        while (System.currentTimeMillis() < deadline) {
            if (condition()) return
            Thread.sleep(150)
        }
        fail("WebView condition timed out")
    }
    private fun awaitLogin(scenario: ActivityScenario<MainActivity>) = awaitCondition {
        evaluate(scenario, "location.origin === 'https://miso.novapixel.org' && document.readyState === 'complete' && !!document.querySelector('input[type=password]')") == "true"
    }

    @Test fun bridgeIsVersionedMainFrameOnlyAndOptionalWithoutFirebase() {
        ActivityScenario.launch(MainActivity::class.java).use { scenario ->
            scenario.onActivity { activity ->
                requireNotNull(web(activity.window.decorView)).loadDataWithBaseURL(BuildConfig.MISO_BASE_URL,
                    "<html><body>Bridge fixture</body></html>","text/html","UTF-8",BuildConfig.MISO_BASE_URL)
            }
            awaitCondition { evaluate(scenario,"document.body?.textContent === 'Bridge fixture' && typeof MisoNative !== 'undefined'") == "true" }
            evaluate(scenario,"""
                window.replyCount = 0;
                MisoNative.onmessage = e => { window.replyCount++; window.replyState = JSON.parse(e.data).state; };
                MisoNative.postMessage(JSON.stringify({v:2,type:'pushStatus',id:'bad-version'}));
                var frame = document.createElement('iframe');
                frame.srcdoc = "<script>MisoNative.onmessage=()=>parent.replyCount++; MisoNative.postMessage(JSON.stringify({v:1,type:'pushStatus',id:'frame'}));</script>";
                document.body.appendChild(frame);
            """.trimIndent())
            Thread.sleep(300)
            assertEquals("0",evaluate(scenario,"replyCount"))
            evaluate(scenario,"MisoNative.postMessage(JSON.stringify({v:1,type:'pushStatus',id:'main'}))")
            awaitCondition { evaluate(scenario,"replyCount") == "1" }
            if (!BuildConfig.FIREBASE_CONFIGURED) assertEquals("\"unconfigured\"",evaluate(scenario,"replyState"))
        }
    }

    @Test fun publicLoginLoadsOverHttps() {
        ActivityScenario.launch(MainActivity::class.java).use { scenario -> awaitLogin(scenario) }
    }

    @Test fun secureSettingsAndRecreation() {
        ActivityScenario.launch(MainActivity::class.java).use { scenario ->
            awaitLogin(scenario)
            scenario.onActivity { activity ->
                val browser = requireNotNull(web(activity.window.decorView))
                assertTrue(browser.settings.javaScriptEnabled)
                assertTrue(browser.settings.domStorageEnabled)
                assertFalse(browser.settings.allowFileAccess)
                assertFalse(browser.settings.allowContentAccess)
                assertEquals(WebSettings.MIXED_CONTENT_NEVER_ALLOW, browser.settings.mixedContentMode)
                assertEquals("MiSo", activity.getString(R.string.app_name))
            }
            val originalUrl = evaluate(scenario, "location.href")
            scenario.recreate()
            awaitLogin(scenario)
            assertEquals(originalUrl, evaluate(scenario, "location.href"))
        }
    }

    @Test fun livewireStyleHistorySupportsBack() {
        ActivityScenario.launch(MainActivity::class.java).use { scenario ->
            awaitLogin(scenario)
            val originalUrl = evaluate(scenario, "location.href")
            // A real touch is necessary: Chromium skips history entries without user activation.
            // This fixture only changes the current DOM/history, with no server writes.
            evaluate(scenario, """
                var button = document.createElement('button');
                button.textContent = 'Android navigation test';
                button.style = 'position:fixed;inset:0;width:100%;height:100%;z-index:2147483647';
                button.onclick = () => { history.pushState({}, '', '/tasks?android_test=1'); button.remove(); };
                document.body.appendChild(button);
            """.trimIndent())
            var x = 0f
            var y = 0f
            scenario.onActivity {
                val browser = requireNotNull(web(it.window.decorView))
                val location = IntArray(2)
                browser.getLocationOnScreen(location)
                x = location[0] + browser.width / 2f
                y = location[1] + browser.height / 2f
            }
            val now = SystemClock.uptimeMillis()
            val instrumentation = InstrumentationRegistry.getInstrumentation()
            MotionEvent.obtain(now, now, MotionEvent.ACTION_DOWN, x, y, 0).also { instrumentation.sendPointerSync(it); it.recycle() }
            MotionEvent.obtain(now, now + 100, MotionEvent.ACTION_UP, x, y, 0).also { instrumentation.sendPointerSync(it); it.recycle() }
            awaitCondition { evaluate(scenario, "location.pathname") == "\"/tasks\"" }
            awaitCondition {
                var canGoBack = false
                scenario.onActivity { canGoBack = requireNotNull(web(it.window.decorView)).canGoBack() }
                canGoBack
            }
            scenario.onActivity { it.onBackPressedDispatcher.onBackPressed() }
            awaitCondition { evaluate(scenario, "location.href") == originalUrl }
        }
    }
}
