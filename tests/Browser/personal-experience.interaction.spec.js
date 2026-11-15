import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync, unlinkSync, existsSync } from 'node:fs';
import path from 'node:path';

const PORT = 8063;
const directory = path.resolve('storage/framework/testing');
const database = path.join(directory, 'personal-experience-browser.sqlite');
let server, fixture, serverLog;
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
    serverLog = openSync(path.join(directory, 'personal-experience-server.log'), 'w');
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
        { cwd: path.resolve('public'), env, stdio: ['ignore', serverLog, serverLog], windowsHide: true });
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
    if (serverLog !== undefined) closeSync(serverLog);
    // Solo archivos exactos de esta fixture dentro del directorio de testing.
    for (const suffix of ['', '-wal', '-shm']) {
        const target = database + suffix;
        if (path.dirname(target) !== directory) throw Error('Unsafe fixture cleanup');
        if (existsSync(target)) unlinkSync(target);
    }
});


for (const width of [390, 1440]) {
    test.describe(`focus viewport ${width}`, () => {
    test.use({ viewport: { width, height: 844 }, isMobile: width === 390, hasTouch: width === 390 });
    test(`dashboard manual focus remains usable ${width}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 844 });
        await page.goto('/login');
        await page.locator('input[type=email]').fill('focus-dashboard@example.test');
        await page.locator('input[type=password]').fill('browser-test-password');
        await page.locator('button[type=submit]').click({ timeout: 60000 });
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/');
        const open = async () => {
            await page.getByRole('button', { name: 'Crear', exact: true }).click();
            await page.getByRole('menuitem', { name: 'Registrar sesión de enfoque', exact: true }).click();
        };
        for (let cycle = 0; cycle < 3; cycle++) {
            await open();
            const dialog = page.locator('#panel-focus-manual-dialog');
            await expect(dialog).toBeVisible();
            await dialog.getByLabel('Inicio', { exact: true }).fill('00:00');
            await dialog.getByLabel('Fin', { exact: true }).fill('00:01');
            await expect(dialog.getByRole('status')).toContainText('0 h 1 min');
            await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
            await expect(dialog).toBeHidden();
            await expect(page.locator('body')).not.toHaveClass(/md-scroll-locked/);
            await expect(page.getByRole('dialog', { name: 'Tienes cambios sin guardar' })).toBeHidden();
        }
        await open();
        await page.locator('#panel-focus-manual-dialog').getByRole('button', { name: 'Cancelar', exact: true }).click();
        await expect(page.locator('#panel-focus-manual-dialog')).toBeHidden();
        await expect(page.locator('body')).not.toHaveClass(/md-scroll-locked/);
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/focus-dashboard-${width}.png` });
        await open();
        const dialog = page.locator('#panel-focus-manual-dialog');
        await dialog.getByLabel('Inicio', { exact: true }).fill('10:00');
        await dialog.getByLabel('Fin', { exact: true }).fill('09:00');
        await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(dialog).toContainText('La fecha y hora de fin debe ser posterior al inicio.');
        await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
        const discard = page.getByRole('dialog', { name: 'Tienes cambios sin guardar', exact: true });
        await expect(discard).toBeVisible();
        await discard.getByRole('button', { name: 'Seguir editando', exact: true }).click();
        await expect(dialog.getByLabel('Inicio', { exact: true })).toHaveValue('10:00');
        await expect(page.locator('body')).toHaveClass(/md-scroll-locked/);
        await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
        await discard.getByRole('button', { name: 'Descartar cambios', exact: true }).click();
        await expect(dialog).toBeHidden();
        await expect(page.locator('body')).not.toHaveClass(/md-scroll-locked/);
        await open();
        await dialog.getByLabel('Inicio', { exact: true }).fill('00:00');
        await dialog.getByLabel('Fin', { exact: true }).fill('00:01');
        await dialog.getByLabel('Descripción (opcional)', { exact: true }).fill('Borrador recuperable');
        const unavailable = async route => {
            if (route.request().postData()?.includes('saveManualSession')) {
                await route.fulfill({ status: 503, contentType: 'text/html', body: '<p>Servicio de prueba temporalmente no disponible</p>' });
            } else await route.continue();
        };
        const updateRoute = /\/livewire[^/]*\/update/;
        await page.route(updateRoute, unavailable);
        await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
        const failure = page.locator('#livewire-error');
        await expect(failure).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(failure).toBeHidden();
        await page.unroute(updateRoute, unavailable);
        await expect(dialog.getByLabel('Descripción (opcional)', { exact: true })).toHaveValue('Borrador recuperable');
        await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(dialog).toBeHidden();
        await expect(page.locator('body')).not.toHaveClass(/md-scroll-locked/);
        await page.getByRole('link', { name: 'Consultar mis objetivos y registrar avances' }).click();
        await expect(page).toHaveURL(/goals/);
        expect(errors).toEqual([]);
    });
    });
}

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
        await expect(page.getByRole('link', { name: 'Volver a Mi día', exact: true })).toHaveCount(0);
        await page.getByRole('button', { name: 'Continuar', exact: true }).click();
        await expect(page.getByRole('alert')).toContainText('Elige al menos una opción.');
        if (width === 390) {
            await page.getByRole('checkbox', { name: 'Trabajo', exact: true }).check();
            await page.getByRole('checkbox', { name: 'Ninguna de estas', exact: true }).check();
            await expect(page.getByRole('checkbox', { name: 'Trabajo', exact: true })).not.toBeChecked();
        } else {
            await page.getByRole('checkbox', { name: 'Trabajo', exact: true }).check();
            await page.getByRole('checkbox', { name: 'Estudio', exact: true }).check();
            await page.getByRole('checkbox', { name: 'Cuido del hogar o de otras personas', exact: true }).check();
        }
        await page.getByRole('button', { name: 'Continuar', exact: true }).click();
        await expect(page.getByRole('heading', { name: '¿Con qué te gustaría empezar?' })).toBeVisible();
        await expect(page.getByRole('heading', { name: '¿Con qué te gustaría empezar?' })).toBeFocused();
        await expect(page.getByRole('textbox')).toHaveCount(0);
        await page.getByRole('radio', { name: width === 390 ? 'Todavía no sé' : 'Aprender o estudiar', exact: true }).check();
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/needs-${width}.png`, fullPage: true });
        expect((await new AxeBuilder({ page }).include('.experience-guide').analyze()).violations).toEqual([]);
        await page.getByRole('button', { name: 'Continuar', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Un hábito pequeño', exact: true })).toBeVisible();
        await page.reload();
        await expect(page.getByRole('heading', { name: 'Un hábito pequeño', exact: true })).toBeVisible();
        await expect(page.getByRole('radio')).toHaveCount(3);
        await page.getByRole('button', { name: 'Ver más ideas', exact: true }).click();
        await expect(page.getByRole('radio')).toHaveCount(12);
        await page.getByRole('radio', { name: 'Leer unos minutos', exact: true }).check();
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/habit-${width}.png`, fullPage: true });
        await page.getByRole('button', { name: 'Preparar hábito', exact: true }).click();
        await page.getByRole('menuitem', { name: 'Preparar hábito', exact: true }).click();
        const dialog = page.getByRole('dialog', { name: 'Preparar mi hábito' });
        await expect(dialog).toBeVisible();
        const dialogBox = await dialog.boundingBox();
        expect(dialogBox.y).toBeGreaterThanOrEqual(0);
        expect(dialogBox.y + dialogBox.height).toBeLessThanOrEqual(901);
        await expect(dialog.getByLabel('Nombre', { exact: false })).toHaveValue('Leer unos minutos');
        await expect(dialog.getByLabel('Momento del día', { exact: false })).toHaveValue('anytime');
        await expect(dialog.getByText('Todos los días. Puedes ajustar este hábito después.')).toBeVisible();
        expect((await new AxeBuilder({ page }).include('#onboarding-habit-dialog').analyze()).violations).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/editor-${width}.png`, fullPage: true });
        // Escape returns to the invoking action without writing a habit.
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await page.getByRole('button', { name: 'Preparar hábito', exact: true }).click();
        await page.getByRole('menuitem', { name: width === 1440 ? 'Crear el mío' : 'Preparar hábito', exact: true }).click();
        await expect(dialog).toBeVisible();
        if (width === 1440) {
            await expect(dialog.getByLabel('Nombre', { exact: false })).toHaveValue('');
            await dialog.getByLabel('Nombre', { exact: false }).fill('á'.repeat(121));
            await dialog.getByRole('button', { name: 'Guardar hábito', exact: true }).click();
            await expect(dialog.getByLabel('Nombre', { exact: false })).toHaveValue('á'.repeat(121));
            await expect(dialog.getByLabel('Nombre', { exact: false })).toHaveAttribute('aria-invalid', 'true');
        }
        await dialog.getByLabel('Nombre', { exact: false }).fill('Leer apuntes durante diez minutos');
        await dialog.getByRole('button', { name: 'Guardar hábito', exact: true }).click();
        await expect(dialog).toBeHidden();
        await expect(page.getByRole('article', { name: 'Tu hábito preparado' })).toContainText('Leer apuntes');
        await page.getByRole('button', { name: 'Continuar', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Ya tienes por dónde empezar' })).toBeVisible();
        const guideAxe = await new AxeBuilder({ page }).include('.experience-guide').analyze();
        expect(guideAxe.violations).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/playwright/personal-experience/summary-${width}.png`, fullPage: true });
        await page.getByRole('button', { name: 'Empezar mi día' }).click();
        await expect(page).not.toHaveURL(/bienvenida/, { timeout: 30000 });
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


test('existing guided journey reuses a habit and allows leaving', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto('/login');
    await page.locator('input[type=email]').fill('existing-guide@example.test');
    await page.locator('input[type=password]').fill('browser-test-password');
    await page.locator('button[type=submit]').click();
    await page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 30000 });
    await page.goto('/bienvenida');
    await page.getByRole('link', { name: 'Volver a Mi día', exact: true }).click();
    await expect(page).not.toHaveURL(/bienvenida/, { timeout: 30000 });
    await page.goto('/bienvenida');
    await page.getByRole('checkbox', { name: 'Cuido del hogar o de otras personas', exact: true }).check();
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await page.getByRole('radio', { name: 'Cuidarme en lo cotidiano', exact: true }).check();
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await page.getByLabel('Usar un hábito que ya tengo').selectOption({ label: 'Mi hábito de siempre' });
    await page.getByRole('button', { name: 'Usar este hábito', exact: true }).click();
    await expect(page.getByRole('article', { name: 'Tu hábito preparado' })).toContainText('Mi hábito de siempre');
    await page.getByRole('button', { name: 'Editar mi hábito', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Preparar mi hábito' });
    await expect(dialog.getByLabel('Nombre', { exact: false })).toHaveValue('Mi hábito de siempre');
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('button', { name: 'Editar mi hábito', exact: true })).toBeFocused();
    await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
    await page.evaluate(() => { document.documentElement.dataset.theme = 'dark'; });
    expect((await new AxeBuilder({ page }).include('.experience-guide').analyze()).violations).toEqual([]);
    await page.screenshot({ path: 'storage/framework/testing/playwright/personal-experience/existing-dark-390.png', fullPage: true });
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await page.getByRole('button', { name: 'Empezar mi día' }).click();
    await expect(page).not.toHaveURL(/bienvenida/, { timeout: 30000 });
    await page.goto('/bienvenida');
    await expect(page.getByRole('heading', { name: 'Ya tienes por dónde empezar' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Volver a Mi día', exact: true })).toBeVisible();
});
