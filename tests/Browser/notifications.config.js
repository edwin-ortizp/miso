import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.', testMatch: 'notifications.interaction.spec.js', workers: 1, timeout: 60000,
    outputDir: '../../storage/framework/testing/playwright/notifications',
    use: { browserName: 'chromium', locale: 'es-ES', timezoneId: 'America/Bogota', reducedMotion: 'reduce', trace: 'retain-on-failure' },
});
