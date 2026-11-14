import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: '.', testMatch: 'game-generations.interaction.spec.js', workers: 1,
    outputDir: '../../storage/framework/testing/playwright/game-generations',
    use: { baseURL: 'http://127.0.0.1:8044', browserName: 'chromium', locale: 'es-CO', reducedMotion: 'reduce', trace: 'retain-on-failure' },
});
