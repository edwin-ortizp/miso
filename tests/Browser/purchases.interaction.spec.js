import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';

// Every test in this suite uses the dedicated fixture server, never the usual database.
test.use({ baseURL: 'http://127.0.0.1:8027' });

let server;
let serverEnv;

async function selectSection(dialog, name) {
    await expect(dialog).toBeVisible();
    const section = dialog.getByRole('button', { name, exact: true });
    if (!await section.isVisible()) await dialog.getByRole('button', { name: 'Mostrar u ocultar secciones' }).click();
    await section.click();
}

async function consumeHome(dialog) {
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('.meal-status-chip--lg')).toHaveText('Planeada');
    const main = dialog.getByRole('button', { name: 'Marcar como consumida', exact: true });
    if (await main.isVisible()) { await main.click(); }
    else {
        await dialog.getByRole('button', { name: 'Más formas de registrar el consumo' }).click();
        await dialog.getByRole('menuitem', { name: 'Marcar como consumida', exact: true }).click();
    }
}

async function deleteBreakfast(page, editor, breakfast) {
    await editor.getByRole('button', { name: 'Cancelar', exact: true }).click();
    const card = breakfast.locator('..');
    await card.getByRole('button', { name: /^Acciones de/ }).click();
    page.once('dialog', dialog => dialog.accept());
    await card.getByRole('menuitem', { name: 'Eliminar', exact: true }).click();
    await expect(breakfast).toContainText('Agregar');
}
test.beforeAll(async () => {
    test.setTimeout(120_000);
    const directory = path.resolve('storage/framework/testing');
    mkdirSync(directory, { recursive: true });
    const database = path.join(directory, 'purchase-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-purchase-config.php') };
    serverEnv = env;
    execFileSync('php', ['tests/Browser/support/purchase-fixture.php'], { env, stdio: 'pipe' });
    server = spawn('php', ['-S', '127.0.0.1:8027', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'pipe', windowsHide: true });
    let serverOutput = '';
    server.stdout.on('data', data => { serverOutput += data; });
    server.stderr.on('data', data => { serverOutput += data; });
    for (let attempt = 0; attempt < 60; attempt++) {
        try { if ((await fetch('http://127.0.0.1:8027/login')).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw new Error(`Isolated purchase server did not start: ${serverOutput}`);
});
test.afterAll(() => { server?.kill(); });

for (const width of [390, 768, 1440]) {
    test(`inventory history document and return at ${width}px`, async ({ page, context }) => {
        test.setTimeout(180_000);
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill('purchase-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        const state = JSON.parse(execFileSync('php', ['tests/Browser/support/purchase-fixture.php', '--history-state'], { env: serverEnv, encoding: 'utf8' }));
        await page.goto(`/meals/inventory?itemId=${state.product}&q=KARDEX-32`);
        const registry = page.locator('#inventory-documents');
        await expect(registry.locator('tbody tr')).toHaveCount(1);
        await expect(registry.locator('td[data-label="Productos"]')).toContainText('32');
        await expect(registry.locator('td[data-label="Líneas"]')).toContainText('32');
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: `storage/framework/testing/purchase-previews/kardex-registry-${width}.png` });
        await registry.getByRole('link', { name: `Abrir documento ${state.id}` }).focus();
        await page.keyboard.press('Enter');
        await expect(page.getByLabel('ID del documento', { exact: true })).toHaveValue(state.id);
        await expect(page.locator('#inventory-document-lines').getByRole('link', { name: 'Ver Kardex', exact: true })).toHaveCount(32);
        await expect(page.getByRole('columnheader', { name: 'Entrada', exact: true })).toHaveCount(1);
        await expect(page.getByRole('columnheader', { name: 'Salida', exact: true })).toHaveCount(1);
        await expect(page.locator('#inventory-document-lines tbody tr')).toHaveCount(32);
        if (width === 390) await expect(page.locator('.inventory-table__label').first()).toBeVisible();
        await page.getByRole('button', { name: 'Copiar ID', exact: true }).click();
        await expect(page.getByRole('button', { name: 'ID copiado', exact: true })).toBeVisible();
        // Audit new content; shared module tabs/sidebar have existing accent contrast debt.
        const violations = (await new AxeBuilder({ page }).include('.md-module-shell[data-module="meals"] .md-module-content').withTags(['wcag2a', 'wcag2aa']).analyze()).violations;
        expect(violations.filter(item => ['serious', 'critical'].includes(item.impact))).toEqual([]);
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: `storage/framework/testing/purchase-previews/kardex-document-${width}.png` });
        await page.locator('#inventory-document-lines').getByRole('link', { name: 'Ver Kardex', exact: true }).first().click();
        await expect(page).toHaveURL(/meals\/ingredients\/[^/]+\/inventory/, { timeout: 30_000 });
        await expect(page.getByText('Stock actual: 2.000 unit', { exact: true })).toBeVisible();
        await expect(page.locator('#inventory-history td[data-label="Saldo posterior"]').first()).toContainText('2.000');
        await expect(page.locator('#inventory-history td[data-label="Unidad"]').first()).toContainText('unit');
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        expect(await page.locator('#inventory-history td .md-btn-text').evaluateAll(links => links.every(link => {
            const cell = link.closest('td').getBoundingClientRect();
            const bounds = link.getBoundingClientRect();
            return bounds.left >= cell.left && bounds.right <= cell.right + 1;
        }))).toBe(true);
        await page.screenshot({ path: `storage/framework/testing/purchase-previews/kardex-history-${width}.png` });
        const historyViolations = (await new AxeBuilder({ page }).include('.md-module-content').withTags(['wcag2a', 'wcag2aa']).analyze()).violations;
        expect(historyViolations.filter(item => ['serious', 'critical'].includes(item.impact))).toEqual([]);
        await page.getByRole('button', { name: 'Filtros', exact: true }).click();
        await page.getByLabel('Canal', { exact: true }).selectOption('mcp');
        await page.keyboard.press('Escape');
        await expect(page.locator('#inventory-history')).toContainText('Sin resultados para estos filtros');
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.getByRole('link', { name: 'Volver', exact: true }).click();
        await expect(page.getByLabel('ID del documento', { exact: true })).toHaveValue(state.id);
        await page.getByRole('link', { name: 'Volver al registro', exact: true }).click();
        await expect(page).toHaveURL(url => url.pathname === '/meals/inventory' && url.searchParams.get('itemId') === state.product && url.searchParams.get('q') === 'KARDEX-32');
        await expect(registry.locator('tbody tr')).toHaveCount(1);
        await expect(registry.locator('td[data-label="Productos"]')).toContainText('32');
        await expect(registry.locator('td[data-label="Líneas"]')).toContainText('32');
        await expect(page.locator('.md-fab')).toHaveCount(0);
        expect(errors).toEqual([]);
    });
}

test('meal inventory concurrent consumers preserve stock', async () => {
    test.setTimeout(60_000);
    const fixture = 'tests/Browser/support/purchase-fixture.php';
    const { ids } = JSON.parse(execFileSync('php', [fixture, '--setup-race'], { env: serverEnv, encoding: 'utf8' }));
    const results = await Promise.all(ids.map(id => new Promise((resolve, reject) => {
        const process = spawn('php', [fixture, '--consume-race', String(id)], { env: serverEnv, windowsHide: true });
        let output = '';
        let errors = '';
        process.stdout.on('data', data => { output += data; });
        process.stderr.on('data', data => { errors += data; });
        process.on('error', reject);
        process.on('close', code => code === 0 ? resolve(JSON.parse(output)) : reject(new Error(errors || output)));
    })));
    expect(results.filter(result => result.consumed)).toHaveLength(1);
    // SQLite rejects competing writers with a database lock; MySQL uses row locks.
    expect(results.find(result => !result.consumed).error).toMatch(/faltan 50|database is locked/);
    const state = JSON.parse(execFileSync('php', [fixture, '--race-state'], { env: serverEnv, encoding: 'utf8' }));
    expect(state).toEqual({ stock: 25, consumed: 1 });
});

for (const viewport of [{ width: 1440, height: 1000 }, { width: 390, height: 844 }]) {
    test(`meal settings catalogs at ${viewport.width}px`, async ({ page }) => {
        test.setTimeout(60_000);
        await page.setViewportSize(viewport);
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill('purchase-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/meals/settings');
        await page.getByRole('button', { name: 'Crear tienda', exact: true }).click();
        const form = page.getByRole('dialog', { name: 'Crear tienda' });
        await form.getByLabel('Nombre').fill(`${viewport.width === 390 ? 'Botica móvil' : 'Mercado escritorio'}`);
        await form.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(form).not.toBeVisible();
        await expect(page.locator('#meal-settings-catalog')).toContainText(`${viewport.width === 390 ? 'Botica móvil' : 'Mercado escritorio'}`);
        await page.getByRole('navigation', { name: 'Secciones de Ajustes de Comidas' }).getByRole('link', { name: 'Categorías de productos' }).click();
        await page.getByRole('button', { name: 'Crear categoría', exact: true }).click();
        const category = page.getByRole('dialog', { name: 'Crear categoría' });
        await category.getByLabel('Nombre').fill(`Categoría navegador ${viewport.width}`);
        await category.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(category).not.toBeVisible();
        await page.reload();
        await expect(page.locator('#meal-settings-catalog')).toContainText(`Categoría navegador ${viewport.width}`);
        const nav = await page.locator('.meal-settings-nav').boundingBox();
        const content = await page.locator('#meal-settings-catalog').boundingBox();
        expect(viewport.width < 768 ? nav.y < content.y : nav.x > content.x + content.width - 1).toBeTruthy();
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
        const accessibility = await new AxeBuilder({ page }).include('.meal-settings-workspace').analyze();
        expect(accessibility.violations).toEqual([]);
        await page.goto('/meals/ingredients');
        await page.getByRole('button', { name: 'Agregar ingrediente', exact: true }).click();
        const ingredient = page.getByRole('dialog', { name: 'Agregar ingrediente' });
        await ingredient.getByLabel('Nombre').fill(`Ingrediente categoría ${viewport.width}`);
        const categoryPicker = ingredient.getByRole('combobox', { name: 'Categoría' });
        await categoryPicker.fill(`CATEGORIA NAVEGADOR ${viewport.width}`);
        await ingredient.getByRole('option', { name: `Categoría navegador ${viewport.width}` }).click();
        await expect(categoryPicker).toHaveValue(`Categoría navegador ${viewport.width}`);
        const categoryId = [await categoryPicker.evaluate(element => window.Alpine.$data(element).value)];
        expect(categoryId[0]).toMatch(/^[0-9a-f-]{36}$/);
        await ingredient.getByLabel('Unidad base').selectOption('g');
        await selectSection(ingredient, 'Variantes');
        await ingredient.getByRole('button', { name: 'Agregar variante' }).click();
        await ingredient.getByLabel('Contenido').fill('500');
        await ingredient.getByRole('button', { name: /^Unidades:/ }).click();
        await expect(ingredient.getByLabel('Unidades por paquete')).toBeVisible();
        await selectSection(ingredient, 'Precios por tienda');
        await ingredient.getByRole('button', { name: 'Agregar precio' }).click();
        await ingredient.getByLabel('Tienda').selectOption({ index: 1 });
        const amount = ingredient.getByLabel('Precio', { exact: true });
        await amount.pressSequentially('4500');
        await expect(amount).toHaveValue('4.500');
        await ingredient.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(ingredient).not.toBeVisible();
        await page.goto(`/meals/ingredients?categories[0]=${categoryId[0]}`);
        await expect(page.locator('#meal-ingredients')).toContainText(`Ingrediente categoría ${viewport.width}`);
        await expect(page.locator('#meal-ingredients')).toContainText(`Categoría navegador ${viewport.width}`);
    });
    test(`meal inventory cook consume and revert at ${viewport.width}px`, async ({ page }) => {
        test.setTimeout(120_000);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize(viewport);
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill('purchase-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/meals/preparations');
        await page.getByRole('button', { name: 'Cocinar preparación', exact: true }).click();
        const cook = page.getByRole('dialog', { name: 'Cocinar preparación' });
        await cook.getByLabel('Receta', { exact: true }).selectOption({ label: 'Sopa de inventario' });
        await cook.getByLabel('Porciones producidas').fill('6');
        await cook.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(cook).not.toBeVisible();
        await expect(page.locator('#meal-preparations')).toContainText('Producidas: 6');
        await page.goto('/meals/weekly');
        const breakfast = page.getByRole('button', { name: /^Desayuno ·/ }).first();
        await breakfast.focus();
        await page.keyboard.press('Enter');
        const editor = page.getByRole('dialog');
        await editor.getByRole('button', { name: 'Agregar', exact: true }).click();
        await editor.locator('#meal-kind-0').selectOption('preparation');
        await editor.locator('#meal-element-0').fill('Sopa de inventario');
        await editor.getByRole('option', { name: 'Sopa de inventario · 6 porciones libres', exact: true }).click();
        await editor.locator('#meal-quantity-0').fill('2');
        await editor.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(editor).not.toBeVisible();
        await breakfast.click();
        await consumeHome(editor);
        await expect(editor.locator('.meal-status-chip--lg')).toHaveText('Consumida');
        await expect(editor.locator('#meal-quantity-0')).toBeDisabled();
        await editor.getByRole('button', { name: 'Volver a planeada', exact: true }).click();
        await expect(editor.locator('.meal-status-chip--lg')).toHaveText('Planeada');
        await deleteBreakfast(page, editor, breakfast);
        await page.goto('/meals/preparations');
        await expect(page.locator('#meal-preparations')).toContainText('Reservadas: 0');
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Cancelar preparación', exact: true }).click();
        await expect(page.locator('#meal-preparations')).not.toContainText('Sopa de inventario');
        await page.goto('/meals/weekly');
        await breakfast.click();
        for (const [index, product, quantity, unit] of [[0, 'Leche de consumo', '250', 'ml'], [1, 'Torta de consumo', '50', 'g']]) {
            await editor.getByRole('button', { name: 'Agregar', exact: true }).click();
            await editor.locator(`#meal-kind-${index}`).selectOption('ingredient');
            await editor.locator(`#meal-element-${index}`).fill(product);
            await editor.getByRole('option', { name: product, exact: true }).click();
            await editor.locator(`#meal-quantity-${index}`).fill(quantity);
            await editor.locator(`#meal-unit-${index}`).selectOption(unit);
        }
        const violations = (await new AxeBuilder({ page }).include('[role="dialog"]').withRules(['button-name', 'label', 'select-name', 'aria-valid-attr-value']).analyze()).violations;
        expect(violations).toEqual([]);
        expect(await editor.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
        await page.screenshot({ path: `storage/framework/testing/purchase-previews/meal-editor-${viewport.width}.png` });
        expect.soft(await editor.locator('.meal-composer-row').evaluateAll(cards => cards.every(card => {
            const bounds = card.getBoundingClientRect();
            return [...card.querySelectorAll('input, select, button, p')].every(child => {
                const rect = child.getBoundingClientRect();
                return rect.left >= bounds.left && rect.right <= bounds.right + 1;
            });
        })), 'Geometría del composer vigente; el CSS de esta superficie no forma parte de CHG-001').toBe(true);
        await consumeHome(editor);
        await expect(editor.locator('.meal-status-chip--lg')).toHaveText('Consumida');
        await editor.getByRole('button', { name: 'Volver a planeada', exact: true }).click();
        await editor.getByRole('button', { name: 'Más formas de registrar el consumo' }).click();
        await editor.getByRole('menuitem', { name: 'Registrar comida por fuera' }).click();
        await editor.getByRole('button', { name: 'Registrar por fuera', exact: true }).click();
        await expect(editor.locator('.meal-status-chip--lg')).toHaveText('Por fuera');
        await editor.getByRole('button', { name: 'Volver a planeada', exact: true }).click();
        await deleteBreakfast(page, editor, breakfast);
        await page.goto('/meals/suggestions');
        await expect(page.getByRole('heading', { name: /^Productos próximos a vencer/ })).toBeVisible();
        expect(errors).toEqual([]);
    });
    test(`meal planning loads dinner editor at ${viewport.width}px`, async ({ page }) => {
        test.setTimeout(60_000);
        await page.setViewportSize(viewport);
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill('purchase-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/meals/weekly');
        await page.locator('.meal-slot').filter({ hasText: 'Sopa de prueba' }).click();
        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await expect(dialog).toContainText('Editar cena');
        await expect(dialog.locator('#meal-element-0')).toHaveValue('Sopa de prueba');
        await selectSection(dialog, 'Resumen nutricional');
        await expect(dialog.locator('#meal-calories')).toHaveValue('420');
        await selectSection(dialog, 'Notas');
        await expect(dialog.locator('#meal-notes')).toHaveValue('Cena de prueba');
        await dialog.locator('#meal-notes').fill('Cena editada');
        await dialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(dialog).not.toBeVisible();
        await page.locator('.meal-slot').filter({ hasText: 'Sopa de prueba' }).click();
        await selectSection(dialog, 'Notas');
        await expect(dialog.locator('#meal-notes')).toHaveValue('Cena editada');
        await dialog.locator('#meal-notes').fill('Cena de prueba');
        await dialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(dialog).not.toBeVisible();
        await page.locator('.meal-slot').filter({ hasText: 'Sopa de prueba' }).click();
        await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
        await expect(dialog).not.toBeVisible();
        await page.getByRole('button', { name: /^Desayuno ·/ }).first().click();
        await expect(dialog).toContainText('Planear desayuno');
        await selectSection(dialog, 'Notas');
        await expect(dialog.locator('#meal-notes')).toHaveValue('');
        await selectSection(dialog, 'Composición');
        await dialog.getByRole('button', { name: 'Agregar', exact: true }).click();
        await dialog.locator('#meal-kind-0').selectOption('free');
        await expect(dialog.locator('#meal-element-0')).toBeVisible();
        expect(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
    });
    test(`management refresh without navigation at ${viewport.width}px`, async ({ page }) => {
        await page.setViewportSize(viewport);
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill('purchase-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/meals/ingredients?q=Pan');
        const card = page.locator('#meal-ingredients');
        const trigger = card.getByRole('button', { name: 'Más acciones de catálogo de ingredientes' });
        await expect(trigger).toHaveCount(1);
        await page.evaluate(() => { window.refreshDocumentMarker = 'same-document'; });
        const updatedStock = viewport.width === 1440 ? '123' : '456';
        execFileSync('php', ['tests/Browser/support/purchase-fixture.php', '--update-stock', updatedStock], { env: serverEnv, stdio: 'pipe' });
        await trigger.focus();
        await page.keyboard.press('Enter');
        const refresh = card.getByRole('menuitem', { name: 'Actualizar tabla' });
        await expect(refresh).toBeVisible();
        let releaseRefresh;
        const heldRequest = new Promise(resolve => { releaseRefresh = resolve; });
        await page.route(/livewire.*\/update/, async route => { await heldRequest; await route.continue(); });
        const refreshRequest = page.waitForRequest(request => request.method() === 'POST' && /livewire.*\/update/.test(request.url()));
        await refresh.focus();
        await page.keyboard.press('Enter');
        await refreshRequest;
        await expect(refresh).toBeDisabled();
        releaseRefresh();
        await expect(card).toContainText(updatedStock);
        await expect(refresh).not.toBeVisible();
        expect(await page.evaluate(() => window.refreshDocumentMarker)).toBe('same-document');
        expect(new URL(page.url()).searchParams.get('q')).toBe('Pan');
        await trigger.click();
        await page.keyboard.press('Escape');
        await expect(trigger).toBeFocused();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    });
    test(`purchase create, detail and edit at ${viewport.width}px`, async ({ page }) => {
        await page.setViewportSize(viewport);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill('purchase-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto('/meals/shopping');
        if (viewport.width < 768) {
            await page.locator('.md-fab-split__compact').click();
        } else {
            await page.getByRole('button', { name: 'Más acciones de creación', exact: true }).click();
        }
        await page.getByRole('menuitem', { name: 'Registrar compra', exact: true }).click();
        const dialog = page.getByRole('dialog', { name: 'Registrar compra' });
        await expect(dialog).toBeVisible();
        await dialog.getByRole('combobox', { name: 'Tienda', exact: true }).selectOption({ label: 'Tienda de prueba' });
        if (viewport.width < 768) await dialog.getByRole('button', { name: 'Mostrar u ocultar secciones' }).click();
        await dialog.getByRole('button', { name: 'Productos', exact: true }).click();
        const line = dialog.locator('fieldset').first();
        await line.getByRole('combobox', { name: 'Producto', exact: true }).selectOption({ label: 'Pan de prueba' });
        await line.getByLabel('Presentación').selectOption({ index: 1 });
        await line.getByRole('spinbutton', { name: 'Paquetes', exact: true }).fill('2');
        await line.getByLabel('Precio final por paquete').fill('10');
        await dialog.getByRole('button', { name: 'Añadir línea' }).click();
        const second = dialog.locator('fieldset').nth(1);
        await second.getByRole('combobox', { name: 'Producto', exact: true }).selectOption({ label: 'Leche de prueba' });
        await second.getByRole('spinbutton', { name: 'Paquetes', exact: true }).fill('1');
        await expect(dialog.getByRole('status')).toContainText('incompleta');
        const violations = (await new AxeBuilder({ page }).include('[role="dialog"]').withRules(['button-name', 'label', 'select-name', 'aria-valid-attr-value']).analyze()).violations;
        expect(violations).toEqual([]);
        expect(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
        await page.screenshot({ path: `storage/framework/testing/purchase-previews/editor-${viewport.width}.png` });
        await dialog.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(dialog).not.toBeVisible();
        await page.goto('/meals/purchases');
        await expect(page.locator('.purchase-history-row').first()).toContainText('2 ítems');
        await page.goto('/meals/purchases?q=Pan&per_page=10');
        const row = page.locator('.purchase-history-row').first();
        await row.focus();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/meals\/purchases\/[^?]+/);
        const detail = page.locator('.purchase-invoice');
        await expect(detail).toContainText('Pan de prueba');
        await expect(detail).toContainText('Suma de ítems (incompleta)');
        await expect(page.getByRole('dialog')).toHaveCount(0);
        const invoiceViolations = (await new AxeBuilder({ page }).include('.purchase-invoice').withRules(['button-name', 'link-name', 'aria-valid-attr-value', 'scrollable-region-focusable']).analyze()).violations;
        expect(invoiceViolations).toEqual([]);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: `storage/framework/testing/purchase-previews/invoice-${viewport.width}.png`, fullPage: true });
        await detail.getByRole('link', { name: 'Pan de prueba' }).click();
        await expect(page).toHaveURL(/ingredient=/);
        await expect(page.locator('#meal-ingredients')).toContainText('Pan de prueba');
        await page.goBack();
        await page.getByRole('link', { name: 'Volver al historial de compras', exact: true }).click();
        await expect(page).toHaveURL(/q=Pan/);
        await expect(page).toHaveURL(/per_page=10/);
        await page.screenshot({ path: `storage/framework/testing/purchase-previews/history-${viewport.width}.png`, fullPage: true });
        await page.locator('.purchase-history-row').first().click();
        await page.getByRole('button', { name: 'Editar compra', exact: true }).click();
        const editor = page.getByRole('dialog', { name: 'Editar compra' });
        await expect(editor).toBeVisible();
        if (viewport.width < 768) await editor.getByRole('button', { name: 'Mostrar u ocultar secciones' }).click();
        await editor.getByRole('button', { name: 'Detalles', exact: true }).click();
        await editor.getByLabel('Notas').fill(`Editada desde ${viewport.width}px`);
        await editor.getByRole('button', { name: 'Guardar', exact: true }).click();
        await expect(editor).not.toBeVisible();
        await expect(page.locator('.purchase-invoice')).toContainText(`Editada desde ${viewport.width}px`);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        expect(errors).toEqual([]);
    });
}
for (const width of [390, 768, 1440]) {
    test(`inventory count validation reason and retry at ${width}px`, async ({ page }) => {
        test.setTimeout(90_000);
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill('purchase-browser@example.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Iniciar Sesión' }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto(`/meals/ingredients?q=${encodeURIComponent(`Inventario navegador ${width}`)}`);
        const row = page.locator('tr').filter({ hasText: `Inventario navegador ${width}` });
        const edit = row.getByRole('button', { name: 'Editar', exact: true });
        if (await edit.isVisible()) { await edit.focus(); await page.keyboard.press('Enter'); }
        else {
            await row.getByRole('button', { name: `Más acciones de Inventario navegador ${width}` }).click();
            await row.getByRole('menuitem', { name: 'Editar', exact: true }).click();
        }
        const dialog = page.locator('#ingredient-dialog');
        await expect(dialog).toBeVisible();
        if (width < 768) await dialog.getByRole('button', { name: 'Mostrar u ocultar secciones' }).click();
        await dialog.getByRole('button', { name: 'Inventario', exact: true }).click();
        const stock = dialog.getByLabel('Existencias contadas');
        // Exercise the server validation as well as HTML's native min/max constraints.
        await stock.evaluate(element => { element.removeAttribute('min'); element.removeAttribute('max'); });
        await stock.fill('-1');
        await dialog.getByLabel('Motivo del cambio').fill(`Conteo navegador ${width}`);
        await dialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('[role="alert"]')).not.toHaveCount(0);
        await expect(stock).toHaveValue('-1');
        await expect(dialog.getByLabel('Motivo del cambio')).toHaveValue(`Conteo navegador ${width}`);
        await stock.fill('10');
        await stock.blur();
        await expect(dialog.getByLabel('Motivo del cambio')).toBeVisible();
        const violations = (await new AxeBuilder({ page }).include('#ingredient-dialog').withTags(['wcag2a', 'wcag2aa']).analyze()).violations;
        expect(violations.filter(item => ['serious', 'critical'].includes(item.impact))).toEqual([]);
        expect(await dialog.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true);
        await page.screenshot({ path: `storage/framework/testing/purchase-previews/inventory-count-${width}.png` });
        let savedRequest;
        page.on('request', request => {
            if (request.method() === 'POST' && /livewire.*\/update/.test(request.url()) && request.postData()?.includes('"method":"save"')) {
                savedRequest = { url: request.url(), body: request.postData(), headers: request.headers() };
            }
        });
        await dialog.getByRole('button', { name: 'Actualizar', exact: true }).click();
        await expect(dialog).not.toBeVisible();
        expect(savedRequest).toBeTruthy();
        const retry = await page.request.post(savedRequest.url, { data: savedRequest.body, headers: { 'content-type': 'application/json', 'x-livewire': 'true', 'x-csrf-token': savedRequest.headers['x-csrf-token'] ?? '' } });
        expect(retry.ok()).toBe(true);
        const state = JSON.parse(execFileSync('php', ['tests/Browser/support/purchase-fixture.php', '--inventory-state', String(width)], { env: serverEnv, encoding: 'utf8' }));
        expect(state).toEqual({ stock: 10, documents: 1 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect(page.locator('#meal-ingredients')).toContainText('10');
    });
}
