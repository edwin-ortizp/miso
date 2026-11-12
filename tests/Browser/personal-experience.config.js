import { defineConfig } from '@playwright/test';
export default defineConfig({ testDir: '.', testMatch: 'personal-experience.interaction.spec.js', workers: 1, timeout: 90000, expect: { timeout: 15000 },
 outputDir: '../../storage/framework/testing/playwright/personal-experience',
 use: { browserName: 'chromium', actionTimeout: 15000, locale: 'es-CO', timezoneId: 'America/Bogota', reducedMotion: 'reduce', trace: 'retain-on-failure' } });
