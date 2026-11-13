package com.edwinortizp.lifetracker
import org.junit.Assert.*
import org.junit.Test
class NotificationEnvelopeTest {
    private val payload = mapOf("version" to "1", "binding" to "binding-a", "notice_id" to "123e4567-e89b-42d3-a456-426614174000")
    @Test fun onlyCurrentEnabledBindingCanDisplay() {
        assertTrue(NativeNotifications.eligible(true,"binding-a",payload))
        assertFalse(NativeNotifications.eligible(false,"binding-a",payload))
        assertFalse(NativeNotifications.eligible(true,"binding-b",payload))
        assertFalse(NativeNotifications.eligible(true,"",payload))
        assertFalse(NativeNotifications.eligible(true,"binding-a",payload + ("version" to "2")))
        assertFalse(NativeNotifications.eligible(true,"binding-a",payload + ("notice_id" to "javascript:alert(1)")))
    }
}
