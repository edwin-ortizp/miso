package com.edwinortizp.lifetracker

import org.junit.Assert.assertEquals
import org.junit.Test
import com.edwinortizp.lifetracker.UrlPolicy.Destination.*

class UrlPolicyTest {
    private val policy = UrlPolicy("https://miso.novapixel.org/")
    @Test fun sameOriginOnly() {
        listOf("https://miso.novapixel.org/", "https://miso.novapixel.org:443/tasks?a=1#item", "https://MISO.NOVAPIXEL.ORG/login")
            .forEach { assertEquals(it, INTERNAL, policy.destination(it)) }
    }
    @Test fun otherHttpsOriginsLeaveTheApp() {
        listOf("https://example.org", "https://miso.novapixel.org.evil.test/", "https://miso.novapixel.org:444/", "https://sub.miso.novapixel.org/")
            .forEach { assertEquals(it, EXTERNAL, policy.destination(it)) }
    }
    @Test fun dangerousAndMalformedUrlsAreBlocked() {
        listOf("http://miso.novapixel.org/", "javascript:alert(1)", "file:///etc/passwd", "content://files/1", "intent://test", "data:text/html,test", "//miso.novapixel.org/", "https://user@miso.novapixel.org/", "https://miso.novapixel.org\\@evil.test", "", "https:///tasks")
            .forEach { assertEquals(it, BLOCKED, policy.destination(it)) }
    }
    @Test fun phoneAndEmailHaveExplicitDestinations() {
        assertEquals(PHONE, policy.destination("tel:+573001234567"))
        assertEquals(EMAIL, policy.destination("mailto:test@example.org"))
    }
}
