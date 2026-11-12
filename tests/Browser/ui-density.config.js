import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: '.', testMatch: 'ui-density.interaction.spec.js', workers: 1,
    outputDir: '../../storage/framework/testing/playwright/ui-density',
    use: { baseURL: 'http://127.0.0.1:8047', browserName: 'chromium', locale: 'es-CO', trace: 'retain-on-failure' },
});
