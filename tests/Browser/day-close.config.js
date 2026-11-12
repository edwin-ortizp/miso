import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.', testMatch: 'day-close.interaction.spec.js', workers: 1, timeout: 60000,
    outputDir: '../../storage/framework/testing/playwright/day-close',
    use: { browserName: 'chromium', locale: 'es-ES', timezoneId: 'America/Bogota', reducedMotion: 'reduce', trace: 'retain-on-failure' },
});
