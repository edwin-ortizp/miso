import { defineConfig } from '@playwright/test';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    testDir: '.', testMatch: 'login.interaction.spec.js', workers: 2, timeout: 30000,
    outputDir: '../../storage/framework/testing/playwright/login',
    use: {
        baseURL: 'http://127.0.0.1:8034', browserName: 'chromium', locale: 'es-CO',
        timezoneId: 'America/Bogota', reducedMotion: 'reduce', trace: 'retain-on-failure',
    },
    webServer: {
        cwd: fileURLToPath(new URL('../..', import.meta.url)),
        command: 'php artisan serve --host=127.0.0.1 --port=8034',
        url: 'http://127.0.0.1:8034/login', reuseExistingServer: !process.env.CI,
        env: { DEBUGBAR_ENABLED: 'false', SESSION_DRIVER: 'file' },
    },
});
