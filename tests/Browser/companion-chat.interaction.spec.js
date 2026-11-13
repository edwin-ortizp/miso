import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync, unlinkSync, existsSync } from 'node:fs';
import path from 'node:path';

const PORT = 8062;
const directory = path.resolve('storage/framework/testing');
const database = path.join(directory, 'companion-voice-browser.sqlite');
let server, fixture;
test.describe.configure({ mode: 'serial' });
test.use({ baseURL: `http://127.0.0.1:${PORT}` });

test.beforeAll(async () => {
    mkdirSync(directory, { recursive: true });
    const compiledViews = path.join(directory, 'companion-voice-browser-views');
    mkdirSync(compiledViews, { recursive: true });
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: `http://127.0.0.1:${PORT}`, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: compiledViews, CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false',
        GAME_IMAGE_BASE_URL: '', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
        APP_CONFIG_CACHE: path.join(directory, 'unused-companion-voice-config.php') };
    fixture = JSON.parse(execFileSync('php', ['tests/Browser/support/companion-voice-fixture.php'], { env, encoding: 'utf8' }));
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
        { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`http://127.0.0.1:${PORT}/login`)).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 300));
    }
    throw Error('Companion voice server did not start');
});

test.afterAll(async () => {
    if (server && server.exitCode === null) {
        const stopped = new Promise(resolve => server.once('exit', resolve));
        server.kill();
        await stopped;
    }
    // Solo archivos exactos de esta fixture dentro del directorio de testing.
    for (const suffix of ['', '-wal', '-shm']) {
        const target = database + suffix;
        if (path.dirname(target) !== directory) throw Error('Unsafe fixture cleanup');
        if (existsSync(target)) unlinkSync(target);
    }
});

async function login(page, email = 'voice@example.test') {
    await page.goto('/login');
    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill('browser-test-password');
    await page.locator('button[type=submit]').click();
    await page.waitForURL(url => !url.pathname.includes('/login'));

}

// CHG-100: el chat del compañero sigue abierto como una conversación.
test('crear varias tareas seguidas sin que el chat se cierre', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto('/tasks/list');
    const launcher = page.locator('body > .lt-chat-host .lt-chat__launcher');
    await launcher.click();
    const panel = page.locator('body > .lt-chat-host .lt-chat__panel');
    await expect(panel).toBeVisible();
    const entry = panel.locator('textarea');
    await expect(entry).toBeFocused();
    for (const [index, title] of ['Primera tarea del chat', 'Segunda tarea del chat'].entries()) {
        await entry.fill(title);
        await entry.press('Enter');
        await expect(panel.locator('.lt-chat__row--pet', { hasText: '¿Algo más?' })).toHaveCount(index + 1);
        await expect(entry).toBeFocused();
        await expect(panel).toBeVisible();
    }
    await expect(page.locator('main, .md-module-shell').first().getByText('Segunda tarea del chat')).toBeVisible();
    await page.screenshot({ path: 'storage/framework/testing/playwright/companion-chat/tasks-1280.png' });
    expect(errors).toEqual([]);
});
