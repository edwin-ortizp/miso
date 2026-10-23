import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.', testMatch: 'tasks-kanban.interaction.spec.js', workers: 1,
    outputDir: '../../storage/framework/testing/playwright/tasks-kanban',
    use: { baseURL: 'http://127.0.0.1:8034', browserName: 'chromium', locale: 'es-CO', timezoneId: 'America/Bogota', trace: 'retain-on-failure' },
});
