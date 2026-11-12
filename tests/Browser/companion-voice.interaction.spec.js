import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync, unlinkSync, existsSync } from 'node:fs';
import path from 'node:path';

const PORT = 8061;
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

async function call(page, component, method, ...args) {
    const id = await page.locator(`[wire\\:name="${component}"]`).first().getAttribute('wire:id');
    await page.evaluate(async ({ id, method, args }) => { await window.Livewire.find(id).call(method, ...args); }, { id, method, args });
}

for (const width of [375, 768, 1280]) {
    test(`companion and objective feedback at ${width}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 900 });
        await login(page);
        await expect(page.locator('.game-buddy-hero__quote')).toBeVisible();
        await page.screenshot({ path: `storage/framework/testing/playwright/companion-voice/home-${width}.png`, fullPage: true });
        await page.goto('/panel');
        const quote = page.locator('.game-buddy-stage__quote');
        const previous = await quote.innerText();
        await page.getByRole('button', { name: 'Otra frase' }).click();
        await expect(quote).not.toHaveText(previous);

        await page.goto(`/goals/${fixture.goal}`);
        await call(page, 'goal.goal-detail', 'updateStatus', 'active');
        const complete = page.getByRole('button', { name: 'Completar', exact: true });
        await complete.focus();
        await page.keyboard.press('Enter');
        const feedback = page.getByTestId('goal-feedback');
        await expect(feedback).toContainText('Objetivo completado');
        await expect(feedback).toHaveAttribute('aria-live', 'polite');
        const message = await feedback.innerText();
        await call(page, 'goal.goal-detail', '$refresh');
        await expect(feedback).toHaveText(message);
        await call(page, 'game.game-notifier', 'check');
        const toastIds = await page.locator('.game-toast__close').evaluateAll(elements => elements.map(el => Number(el.getAttribute('wire:click').match(/\d+/)[0])));
        for (const id of toastIds) await call(page, 'game.game-notifier', 'dismiss', id);
        await page.mouse.move(0, 890);
        await expect(page.locator('.game-toast')).toHaveCount(0, { timeout: 12000 });
        await page.waitForFunction(() => document.getAnimations().every(animation => animation.playState !== 'running'));
        await expect(page.locator('.game-celebration')).toHaveCount(0, { timeout: 10000 });
        expect(await feedback.locator('script, em, b').count()).toBe(0);
        const a11y = await new AxeBuilder({ page }).include('[data-testid="goal-feedback"]').analyze();
        expect(a11y.violations).toEqual([]);
        const overflow = await feedback.evaluate(el => el.scrollWidth > el.clientWidth + 1 || el.getBoundingClientRect().right > innerWidth + 1);
        expect(overflow).toBe(false);
        await page.screenshot({ path: `storage/framework/testing/playwright/companion-voice/goal-${width}.png`, fullPage: true });
        expect(errors).toEqual([]);
    });
}

test('routine stages stay stable, pause restores the phrase and zero does not complete a habit', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 900 });
    await login(page);
    await page.goto('/habits');
    await call(page, 'habit.habit-tracker', 'startRoutine', 'morning');
    const routine = page.locator('.habit-routine[role="dialog"]');
    await expect(routine).toBeVisible();
    const bubble = routine.locator('.habit-routine__bubble');
    const phrase = await bubble.innerText();
    await page.waitForTimeout(1200);
    await expect(bubble).toHaveText(phrase);
    await routine.getByRole('button', { name: 'Pausar', exact: true }).click();
    await expect(bubble).toHaveText('En pausa. Continúa cuando quieras.');
    await routine.getByRole('button', { name: 'Seguir', exact: true }).click();
    await expect(bubble).toHaveText(phrase);
    // Avanzar el reloj local permite probar etapas sin esperar un minuto real.
    const setRemaining = async value => routine.locator('.habit-routine__step').evaluate((el, left) => { window.Alpine.$data(el).left = left; }, value);
    await setRemaining(30);
    const middle = await bubble.innerText();
    await setRemaining(29);
    await expect(bubble).toHaveText(middle);
    await setRemaining(15);
    await expect(bubble).toContainText(/Queda poco tiempo|Últimos segundos/);
    await setRemaining(0);
    await expect(bubble).toContainText(/Márcalo cuando termines|Marca el hábito/);
    await expect(routine.getByRole('heading', { name: /Respirar con calma/ })).toBeVisible();
    await page.screenshot({ path: 'storage/framework/testing/playwright/companion-voice/routine-375.png', fullPage: true });
    await routine.getByRole('button', { name: 'Hecho', exact: true }).click();
    await expect(routine.getByRole('heading', { name: 'Estirar', exact: true })).toBeVisible();
    await setRemaining(10);
    await expect(bubble).toContainText(/Queda poco tiempo|Últimos segundos/);
    await routine.getByRole('button', { name: 'Saltar', exact: true }).click();
    await expect(routine.getByRole('heading', { name: 'Leer', exact: true })).toBeVisible();
    await expect(routine.locator('.habit-routine__clock')).toHaveCount(0);
    await routine.getByRole('button', { name: 'Saltar', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Rutina terminada' })).toBeVisible();
    await expect(page.getByText('Hiciste 1 de 3')).toBeVisible();
});

for (const width of [375, 1280]) {
    test(`six empty states preserve creation dialogs at ${width}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await login(page, 'voice-empty@example.test');
        const states = [
            ['habits', '/habits/settings', 'Yo me encargo de los aplausos.', 'Nuevo hábito'],
            ['goals', '/goals?status=all', '*ya se emocionó*', 'Agregar objetivo'],
            ['tasks', '/tasks/list?status=all', '¿Algo dando vueltas por tu cabeza?', 'Nueva tarea'],
            ['plans', '/plans', '*ya está buscando qué ponerse*', 'Agregar plan'],
            ['recipes', '/meals/recipes', '*se pone el delantal*', 'Crear receta'],
            ['vehicles', '/vehicles', '*reserva sitio*', 'Añadir vehículo'],
        ];
        for (const [key, url, message, action] of states) {
            const response = await page.goto(url);
            expect(response.status()).toBe(200);
            await expect(page.getByText(message, { exact: false }).first()).toBeVisible();
            await page.waitForFunction(() => document.getAnimations().every(animation => animation.playState !== 'running'));
            await page.screenshot({ path: `storage/framework/testing/playwright/companion-voice/empty-${key}-${width}.png`, fullPage: true });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), key).toBe(true);
            const create = page.getByRole('button', { name: action, exact: true });
            await create.click();
            if (await create.getAttribute('aria-haspopup') === 'menu') {
                await page.getByRole('menuitem', { name: action, exact: true }).click();
            }
            await expect(page.getByRole('dialog').last(), key).toBeVisible();
            await page.keyboard.press('Escape');
        }
    });
}
