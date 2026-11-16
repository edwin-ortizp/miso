import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

let server, fuel;
test.beforeAll(async () => {
    test.setTimeout(60000);
    const directory = path.resolve('storage/framework/testing'); mkdirSync(directory, { recursive: true });
    const database = path.join(directory, 'energy-browser.sqlite'); closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-energy-config.php') };
    ({ fuel } = JSON.parse(execFileSync('php', ['tests/Browser/support/energy-fixture.php'], { env, encoding: 'utf8' })));
    server = spawn('php', ['-S', '127.0.0.1:8029', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'pipe', windowsHide: true });
    let serverLog = '';
    server.stderr.on('data', chunk => { serverLog = (serverLog + chunk).slice(-6000); });
    server.stdout.on('data', chunk => { serverLog = (serverLog + chunk).slice(-6000); });
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch('http://127.0.0.1:8029/login', { signal: AbortSignal.timeout(1000) })).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw Error(`Server did not start: ${serverLog}`);
});
test.afterAll(() => server?.kill());

for (const viewport of [{ width: 1440, height: 900 }, { width: 768, height: 1024 }, { width: 390, height: 844 }, { width: 667, height: 375 }]) {
    test(`fuel typing, save and modal geometry ${viewport.width}x${viewport.height}`, async ({ page }) => {
        test.setTimeout(90000);
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize(viewport); await page.goto('/login');
        await page.getByLabel('Correo electrónico', { exact: true }).fill('energy-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto(fuel);
        await page.locator('[wire\\:click="openEnergyForm"]').click();
        const dialog = page.locator('#vehicle-energy-dialog');
        await expect(dialog).toBeVisible();
        expect(errors).toEqual([]);
        const visibleActions = async () => {
            for (const name of ['Cerrar', 'Guardar registro', 'Cancelar']) {
                const box = await dialog.getByRole('button', { name, exact: true }).boundingBox();
                expect(box.y).toBeGreaterThanOrEqual(0);
                expect(box.y + box.height).toBeLessThanOrEqual((await page.evaluate(() => innerHeight)) + 1);
            }
        };
        await visibleActions();
        let requests = 0; page.on('request', request => { if (request.url().includes('/livewire/update')) requests++; });
        const price = dialog.getByLabel(/^Precio por/), cost = dialog.getByLabel(/^Costo total/), quantity = dialog.getByLabel(/^Cantidad/);
        await price.pressSequentially('15000'); await cost.pressSequentially('95000,50');
        expect(errors).toEqual([]);
        await expect(cost).toHaveValue('95000,50'); await expect(quantity).toHaveValue('6.33');
        expect(requests).toBe(0);
        await cost.fill('95,'); await expect(cost).toHaveValue('95,');
        await cost.pressSequentially('50'); await expect(cost).toHaveValue('95,50');
        await cost.fill('95000'); await cost.fill(''); await expect(cost).toHaveValue('');
        await price.focus(); await expect(cost).toHaveValue('94950');
        await cost.fill('95000,50');
        await dialog.getByLabel('Proveedor', { exact: true }).fill('Estación navegador');
        // An unrelated server update must not replace the pricing draft.
        await dialog.getByLabel('Tipo', { exact: true }).selectOption('electrico');
        await expect(dialog.locator('.input-group-text')).toHaveText('kWh');
        await expect(cost).toHaveValue('95000,50');
        if (viewport.width < 768) {
            await page.setViewportSize({ width: viewport.width, height: 420 });
            await visibleActions();
            await dialog.locator('.md-form-dialog__body').evaluate(element => { element.scrollTop = element.scrollHeight; });
            await visibleActions();
            await page.setViewportSize(viewport);
        }
        await dialog.getByRole('button', { name: 'Expandir', exact: true }).click(); await visibleActions();
        await dialog.getByRole('button', { name: 'Restaurar', exact: true }).click(); await visibleActions();
        const a11y = await new AxeBuilder({ page }).include('#vehicle-energy-dialog').withRules(['button-name', 'label', 'aria-valid-attr-value']).analyze();
        expect(a11y.violations).toEqual([]);
        await page.screenshot({ path: `storage/framework/testing/playwright/energy/fuel-${viewport.width}.png`, fullPage: true });
        await page.route('**/livewire/update', async route => { await new Promise(resolve => setTimeout(resolve, 200)); await route.continue(); });
        await dialog.getByRole('button', { name: 'Guardar registro', exact: true }).click();
        await expect(dialog).toHaveCount(0);
        await expect(page.getByText('Estación navegador').filter({ visible: true }).first()).toBeVisible();
        expect(errors).toEqual([]);
    });
}

for (const width of [390, 1440]) test(`shared modal variants ${width}`, async ({ page }) => {
    test.setTimeout(90000);
    page.setDefaultTimeout(10000);
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width, height: width === 390 ? 420 : 900 });
    await page.goto('/login');
    await page.getByLabel('Correo electrónico', { exact: true }).fill('energy-browser@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
    await page.waitForURL(url => !url.pathname.includes('/login'));
    for (const [url, action, selector] of [
        ['/vehicles', 'openVehicleForm', '.vehicle-form-dialog'],
        ['/vehicles/maintenance-catalog', 'openTemplateForm', '.vehicle-template-dialog'],
    ]) {
        await page.goto(url); await page.locator(`[wire\\:click="${action}"]`).click();
        const dialog = page.locator(selector); await expect(dialog).toBeVisible();
        const head = dialog.locator('.md-dialog-mobile-head'), footer = dialog.locator('.md-dialog-actions');
        const originalHead = await head.boundingBox(), originalFooter = await footer.boundingBox();
        if (width < 768) {
            await dialog.locator('.md-dialog-mobile-body').evaluate(element => { element.scrollTop = element.scrollHeight; });
            expect((await head.boundingBox()).y).toBe(originalHead.y);
            expect((await footer.boundingBox()).y).toBe(originalFooter.y);
            expect(originalFooter.y + originalFooter.height).toBeLessThanOrEqual(421);
        } else {
            const rect = await dialog.boundingBox(); expect(rect.width).toBe(760);
        }
        await dialog.getByRole('button', { name: 'Cerrar', exact: true }).click(); await expect(dialog).toHaveCount(0);
    }
    await page.goto('/meals/ingredients');
    await page.getByRole('button', { name: 'Más acciones', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Tiendas', exact: true }).click();
    const large = page.getByRole('dialog'); await expect(large).toBeVisible();
    if (width < 768) {
        await expect(large.locator('.md-dialog-mobile-close')).toBeVisible();
        await large.locator('.md-dialog-mobile-close').click();
    } else {
        await expect(large.locator('.md-dialog-mobile-close')).toBeHidden();
        await page.keyboard.press('Escape');
    }
    await expect(large).toHaveCount(0);
    await page.goto('/health');
    await page.getByRole('button', { name: /Registrar evento/ }).click();
    if (width < 768) await page.getByRole('menuitem', { name: 'Registrar evento', exact: true }).click();
    const health = page.locator('#health-event-dialog'); await expect(health).toBeVisible();
    for (const name of ['Cerrar', 'Cancelar', 'Guardar']) {
        const box = await health.getByRole('button', { name, exact: true }).boundingBox();
        expect(box.y).toBeGreaterThanOrEqual(0); expect(box.y + box.height).toBeLessThanOrEqual(width === 390 ? 421 : 901);
    }
    await health.getByRole('button', { name: 'Detalles adicionales', exact: true }).click();
    await health.getByRole('button', { name: 'Cerrar', exact: true }).click(); await expect(health).toBeHidden();
    await page.goto('/meals/ingredients');
    await page.getByRole('button', { name: /Agregar varios/ }).click();
    const assistant = page.locator('.ingredient-assistant'); await expect(assistant).toBeVisible();
    const header = await assistant.locator('.md-dialog-header').boundingBox();
    const actions = await assistant.locator('.md-dialog-actions').boundingBox();
    expect(header.y).toBeGreaterThanOrEqual(0); expect(actions.y + actions.height).toBeLessThanOrEqual(width === 390 ? 421 : 901);
    await assistant.getByRole('button', { name: 'Cerrar', exact: true }).click(); await expect(assistant).toBeHidden();
    for (const url of ['/tasks/list', '/tasks/flow']) {
        await page.goto(url);
        await page.getByRole('button', { name: url.endsWith('list') ? 'Nueva tarea' : 'Editar tarea: Tarea de prueba de modal', exact: true }).click();
        const task = page.locator('.lt-cm-modal--wide').filter({ visible: true }); await expect(task).toBeVisible();
        for (const selector of ['.md-dialog-header', '.md-dialog-actions']) {
            const box = await task.locator(selector).boundingBox();
            expect(box.y).toBeGreaterThanOrEqual(0); expect(box.y + box.height).toBeLessThanOrEqual(width === 390 ? 421 : 901);
        }
        await task.getByRole('button', { name: 'Cerrar', exact: true }).click(); await expect(task).toHaveCount(0);
    }
    expect(errors).toEqual([]);
});
