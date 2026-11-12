import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.', testMatch: 'companion-voice.interaction.spec.js', workers: 1, timeout: 60000,
    outputDir: '../../storage/framework/testing/playwright/companion-voice',
    use: { browserName: 'chromium', locale: 'es-CO', timezoneId: 'America/Bogota', reducedMotion: 'reduce', trace: 'retain-on-failure' },
});
