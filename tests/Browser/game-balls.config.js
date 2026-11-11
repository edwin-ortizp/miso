import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: '.', testMatch: 'game-balls.interaction.spec.js', workers: 1,
    outputDir: '../../storage/framework/testing/playwright/game-balls',
    use: { baseURL: 'http://127.0.0.1:8038', browserName: 'chromium', locale: 'es-CO', trace: 'retain-on-failure' },
});
