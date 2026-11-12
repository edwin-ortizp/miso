import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync, unlinkSync, existsSync } from 'node:fs';
import path from 'node:path';

const PORT = 8063;
const directory = path.resolve('storage/framework/testing');
const database = path.join(directory, 'personal-experience-browser.sqlite');
let server, fixture;
test.describe.configure({ mode: 'serial' });
test.use({ baseURL: `http://127.0.0.1:${PORT}` });

test.beforeAll(async () => {
    mkdirSync(directory, { recursive: true });
    const compiledViews = path.join(directory, 'personal-experience-browser-views');
    mkdirSync(compiledViews, { recursive: true });
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: `http://127.0.0.1:${PORT}`, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: compiledViews, CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false',
        GAME_IMAGE_BASE_URL: '', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
        APP_CONFIG_CACHE: path.join(directory, 'unused-personal-experience-config.php') };
    fixture = JSON.parse(execFileSync('php', ['tests/Browser/support/personal-experience-fixture.php'], { env, encoding: 'utf8' }));
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
        { cwd: path.resolve('public'), env, stdio: 'ignore', windowsHide: true });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`http://127.0.0.1:${PORT}/login`)).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 300));
    }
    throw Error('Personal experience server did not start');
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


for (const width of [390, 768, 1440]) {
    test(`new user journey and account menu at ${width}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.getByRole('link', { name: /Regístrate/ }).click();
        await page.locator('input[name=name]').fill('Estudiante con un nombre bastante largo');
        await page.locator('input[name=email]').fill(`student-${width}@example.test`);
        await page.locator('input[name=password]').fill('browser-test-password');
        await page.locator('input[name=password_confirmation]').fill('browser-test-password');
        await page.locator('button[type=submit]').click();
        await expect(page).toHaveURL(/bienvenida/);
        await page.waitForFunction(() => window.Livewire && window.Alpine && document.querySelector('[wire\\:id]')?.__livewire);
        await expect(page.getByRole('navigation', { name: 'Tu recorrido de bienvenida' })).toBeVisible();
        await expect(page.getByRole('navigation', { name: 'Destinos principales' })).toHaveCount(0);
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/welcome-${width}.png`, fullPage: true });
        const firstAxe = await new AxeBuilder({ page }).include('.experience-guide').analyze();
        expect(firstAxe.violations).toEqual([]);
        await page.getByRole('radio', { name: 'Estudio', exact: true }).check();
        await page.getByLabel('¿Qué te gustaría mejorar?').fill('Mantener una rutina de estudio');
        await page.getByRole('button', { name: 'Continuar', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'MiSo a tu medida' })).toBeVisible();
        await expect(page.getByRole('checkbox', { name: 'Pomodoro', exact: true })).toBeChecked();
        await page.getByText('Explorar más módulos', { exact: true }).click();
        await expect(page.getByRole('checkbox', { name: 'Vehículos', exact: true })).toBeVisible();
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/modules-${width}.png`, fullPage: true });
        await page.reload();
        await expect(page.getByRole('heading', { name: 'MiSo a tu medida' })).toBeVisible();
        await page.getByRole('button', { name: 'Guardar y continuar' }).click();
        await expect(page.getByRole('heading', { name: 'Tu primer hábito', exact: true })).toBeVisible();
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/habit-${width}.png`, fullPage: true });
        await page.getByRole('button', { name: 'Crear mi hábito', exact: true }).click();
        const dialog = page.getByRole('dialog', { name: 'Nuevo hábito' });
        await expect(dialog).toBeVisible();
        await dialog.getByLabel('Nombre', { exact: false }).fill('Leer apuntes durante diez minutos');
        await dialog.getByRole('button', { name: 'Crear hábito', exact: true }).click();
        await expect(page.getByRole('article', { name: 'Vista previa del hábito' })).toContainText('Leer apuntes');
        await page.getByRole('button', { name: 'Continuar', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Un siguiente paso, si quieres' })).toBeVisible();
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/optional-${width}.png`, fullPage: true });
        await page.getByRole('button', { name: 'Continuar al resumen' }).click();
        await expect(page.getByRole('heading', { name: 'Ya tienes por dónde empezar' })).toBeVisible();
        const guideAxe = await new AxeBuilder({ page }).include('.experience-guide').analyze();
        expect(guideAxe.violations).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/summary-${width}.png`, fullPage: true });
        await page.getByRole('button', { name: 'Empezar mi día' }).click();
        await expect(page).not.toHaveURL(/bienvenida/);
        await expect(page.getByText('Leer apuntes durante diez minutos', { exact: true })).toBeVisible();
        await expect(page.getByText('Registro rápido', { exact: true })).toHaveCount(0);
        await expect(page.getByText('Bitácora del día', { exact: true })).toHaveCount(0);
        const trigger = page.getByRole('button', { name: 'Abrir menú de perfil' });
        await trigger.click();
        const account = page.getByRole('dialog', { name: 'Tu cuenta' });
        await expect(account).toBeVisible();
        await expect(account).toHaveCSS('opacity', '1');
        const box = await account.boundingBox();
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(width + 1);
        expect(box.y + box.height).toBeLessThanOrEqual(901);
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/account-${width}.png` });
        await page.keyboard.press('Escape');
        await expect(account).toBeHidden();
        await expect(trigger).toBeFocused();
        // A Livewire update must preserve the popover's positioning and behavior.
        const id = await page.locator('[wire\\:name="home.dashboard"]').getAttribute('wire:id');
        await page.evaluate(async id => { await window.Livewire.find(id).call('$refresh'); }, id);
        await trigger.click();
        await expect(account).toBeVisible();
        const updatedBox = await account.boundingBox();
        expect(updatedBox.x).toBeGreaterThanOrEqual(0);
        expect(updatedBox.x + updatedBox.width).toBeLessThanOrEqual(width + 1);
        await account.getByRole('link', { name: 'Mi perfil' }).click();
        await expect(page).toHaveURL(/settings/);
        await page.getByRole('link', { name: 'Personalizar módulos', exact: true }).last().click();
        await page.getByRole('checkbox', { name: 'Hábitos', exact: true }).uncheck();
        await page.getByRole('checkbox', { name: 'Vehículos', exact: true }).check();
        await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.getByRole('button', { name: 'Guardar preferencias' }).click()]);
        await page.goto('/');
        await expect(page.getByText('Leer apuntes durante diez minutos', { exact: true })).toHaveCount(0);
        await page.waitForFunction(() => window.Livewire && document.querySelector('[wire\\:id]')?.__livewire);
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
        const axe = await new AxeBuilder({ page }).include('.experience-account').analyze();
        expect(axe.violations).toEqual([]);
        await page.getByRole('button', { name: 'Abrir menú de perfil' }).click();
        await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
        await expect(page).toHaveURL(/login/);
        expect(errors).toEqual([]);
    });
}
