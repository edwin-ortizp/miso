import { defineConfig } from '@playwright/test';
import login from './login.config.js';

export default defineConfig({
    ...login,
    testMatch: 'register.interaction.spec.js',
    outputDir: '../../storage/framework/testing/playwright/register',
});
