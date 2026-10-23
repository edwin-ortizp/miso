import { defineConfig } from '@playwright/test';
export default defineConfig({testDir: '.', testMatch: 'panel.interaction.spec.js', workers: 1,
 outputDir: '../../storage/framework/testing/playwright/panel',
 use: {baseURL: 'http://127.0.0.1:8044', browserName: 'chromium', locale: 'es-CO', timezoneId: 'America/Bogota', trace: 'retain-on-failure'}});
