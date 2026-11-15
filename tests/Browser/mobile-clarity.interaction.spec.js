import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync, unlinkSync, existsSync } from 'node:fs';
import path from 'node:path';

const PORT = 8072;
const directory = path.resolve('storage/framework/testing');
const database = path.join(directory, 'mobile-clarity-browser.sqlite');
let server, fixture;
test.describe.configure({ mode: 'serial' });
test.use({ baseURL: `http://127.0.0.1:${PORT}` });

test.beforeAll(async () => {
    mkdirSync(directory, { recursive: true });
    const compiledViews = path.join(directory, 'mobile-clarity-browser-views');
    mkdirSync(compiledViews, { recursive: true });
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', APP_URL: `http://127.0.0.1:${PORT}`, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        VIEW_COMPILED_PATH: compiledViews, CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false',
        GAME_IMAGE_BASE_URL: '', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
        APP_CONFIG_CACHE: path.join(directory, 'unused-mobile-clarity-config.php') };
    fixture = JSON.parse(execFileSync('php', ['tests/Browser/support/mobile-clarity-fixture.php'], { env, encoding: 'utf8' }));
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

async function capture(page, name, width) {
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({path:`storage/framework/testing/playwright/mobile-clarity/after-${name}-${width}.png`, fullPage:true, animations:'disabled'});
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
}

for (const width of [390, 768, 1440]) {
 test(`claridad móvil ${width}`, async ({page}) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.setViewportSize({width, height: 1000});
    await login(page);
    await page.goto('/');
    const greeting = page.locator('.game-buddy-hero__greeting');
    await expect(greeting).toBeVisible();
    const greetingArt = await greeting.locator('.game-buddy-hero__art').boundingBox();
    const greetingBody = await greeting.locator('.game-buddy-hero__body').boundingBox();
    expect(greetingArt.x).toBeGreaterThan(greetingBody.x + greetingBody.width);
    await capture(page, 'home-companion', width);
    await page.goto('/habits');
    const guardian = page.locator(width < 1200 ? '.game-guardian--top' : '.game-guardian--rail');
    await expect(guardian).toBeVisible();
    const art = await guardian.locator('.game-guardian__art').boundingBox();
    const bubble = await guardian.locator('.game-guardian__bubble').boundingBox();
    expect(art.x).toBeGreaterThan(bubble.x + bubble.width);
    const morning = page.locator('.habit-period--morning');
    const toggle = morning.locator('.habit-period__toggle');
    if (await toggle.getAttribute('aria-expanded') === 'false') await toggle.click();
    const habit = morning.locator('.habit-list-action').first();
    const before = await habit.getAttribute('aria-pressed');
    await habit.click();
    await expect(habit).toHaveAttribute('aria-pressed', before === 'true' ? 'false' : 'true');
    await expect(guardian.locator('.md-progress-label')).toContainText(before === 'true' ? '0 / 4' : '1 / 4');
    await expect(guardian).toBeVisible();
    await toggle.click();
    await expect(morning.locator('.habit-routine-start')).toBeHidden();
    await toggle.click();
    await expect(morning.locator('.habit-routine-start')).toBeVisible();
    await capture(page, 'habits', width);
    const habitAxe = await new AxeBuilder({page}).include('.habit-period').include('.game-guardian').analyze();
    expect(habitAxe.violations).toEqual([]);

    await page.goto('/tasks/calendar');
    await expect(page.locator('.fc-view-harness')).toBeVisible();
    await expect(page.locator('.md-calendar-applied-filters')).toBeHidden();
    if (width < 768) await page.locator('#calendar-view').selectOption('timeGridDay');
    else await page.getByRole('button', {name:'Día', exact:true}).click();
    await expect(page.locator('.fc-timeGridDay-view')).toBeVisible();
    await page.getByRole('button', {name:'Filtros', exact:false}).click();
    const filters = page.locator('#calendar-filters');
    await expect(filters).toBeVisible();
    await filters.getByLabel('Comidas', {exact:true}).uncheck();
    await filters.getByRole('button', {name:'Filtrar', exact:true}).click();
    await expect(filters).toBeHidden();
    await expect(page.locator('.md-calendar-applied-filters')).toBeVisible();
    await expect(page.locator('.md-calendar-applied-filters .md-chip')).toContainText('Ocultos: Comidas');
    await expect(page.locator('.md-calendar-period > i.bi-calendar4')).toBeVisible();
    await expect(page.locator('.md-calendar-period')).toHaveCSS('font-weight', '700');
    const badge = page.locator('.md-calendar-filter-button .md-count-badge');
    await expect(badge).toBeVisible();
    await expect(badge).toHaveText('1');
    const badgeBounds = await badge.boundingBox();
    expect(badgeBounds.width).toBe(badgeBounds.height);
    await expect(badge).toHaveCSS('border-radius', '50%');
    if (width < 768) {
        await expect(page.locator('#calendar-view')).toHaveCSS('height', '44px');
        await expect(page.locator('label[for="calendar-view"]')).toHaveText('Vista:');
    }
    const period = await page.locator('.md-calendar-period').boundingBox();
    const controls = await page.locator('.md-calendar-controls').boundingBox();
    expect(controls.y).toBeGreaterThanOrEqual(period.y + period.height);
    await capture(page, 'calendar', width);
    const calendarAxe = await new AxeBuilder({page}).include('.md-calendar-compact-toolbar').analyze();
    expect(calendarAxe.violations).toEqual([]);
    await page.getByRole('button', {name:'Quitar filtro de tipos de elemento'}).click();
    await expect(page.locator('.md-calendar-applied-filters')).toBeHidden();
    await page.reload();
    await expect(page.locator('.fc-view-harness')).toBeVisible();
    await expect(page.locator('.md-calendar-applied-filters')).toBeHidden();
    await capture(page, 'calendar-default', width);
    await page.getByRole('button', {name:'Periodo siguiente'}).click();
    await page.getByRole('button', {name:'Ahora', exact:true}).click();

    await page.goto('/pomodoro');
    const focus = page.locator('.pomodoro-stage .focus-companion');
    await expect(focus).toBeVisible();
    const focusArt = await focus.locator('.focus-companion__art').boundingBox();
    const focusBubble = await focus.locator('.focus-companion__bubble').boundingBox();
    if (focusArt.y + focusArt.height <= focusBubble.y) {
        expect(focusArt.y).toBeLessThan(focusBubble.y);
    } else {
        expect(focusArt.x).toBeGreaterThan(focusBubble.x + focusBubble.width);
    }
    await capture(page, 'pomodoro', width);
    await focus.getByRole('button', {name:'Ocultar compañero'}).click();
    await expect(focus.locator('.focus-companion__art')).toBeHidden();
    await focus.getByRole('button', {name:'Mostrar compañero'}).click();
    await expect(focus.locator('.focus-companion__art')).toBeVisible();
    expect(errors).toEqual([]);
 });
}

test('sin compañero conserva estado vacío y no desborda', async ({page}) => {
    await page.setViewportSize({width:390, height:1000});
    await login(page, 'voice-empty@example.test');
    await page.goto('/habits');
    await expect(page.locator('.habit-coach-card')).toBeVisible();
    await capture(page, 'habits-empty', 390);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('el progreso del guardián corresponde a la fecha seleccionada', async ({page}) => {
    await page.setViewportSize({width:390, height:1000});
    await login(page);
    await page.goto(`/habits?date=${fixture.yesterday}`);
    const guardian = page.locator('.game-guardian--top');
    await expect(guardian.locator('.md-progress-label')).toContainText('0 / 4');
    await page.locator('.habit-period--morning .habit-list-action').first().click();
    await expect(guardian.locator('.md-progress-label')).toContainText('1 / 4');
    await page.goto(`/habits?date=${fixture.tomorrow}`);
    await expect(guardian.locator('.md-progress-label')).toContainText('0 / 4');
});

test('herramientas del header equidistantes y búsqueda/menús operativos', async ({page}) => {
    await login(page);
    for (const width of [320, 360, 390, 430, 768, 1440]) {
        await page.setViewportSize({width, height:900});
        await page.goto('/habits');
        const header = page.locator('.lt-topbar');
        if (width < 768) {
            const controls = header.locator('.lt-topbar__tools').locator('button[aria-label="Buscar"], a[aria-label="Ajustes"], button.lt-topbar__bell, button.lt-avatar');
            await expect(controls).toHaveCount(4);
            const boxes = await controls.evaluateAll(elements => elements.map(el => { const r=el.getBoundingClientRect(); return {x:r.x,y:r.y,w:r.width,h:r.height}; }));
            for (const box of boxes) { expect(box.w, JSON.stringify(boxes)).toBe(44); expect(box.h, JSON.stringify(boxes)).toBe(44); }
            for (let i=1;i<boxes.length;i++) {
                expect(Math.abs(boxes[i].x-boxes[i-1].x-44)).toBeLessThan(1);
                expect(Math.abs(boxes[i].y-boxes[0].y)).toBeLessThan(1);
            }
            await header.getByRole('button', {name:'Buscar', exact:true}).click();
            await expect(page.locator('#lt-global-search')).toBeFocused();
            await page.locator('#lt-global-search').fill('Hábitos');
            await expect(page.locator('.lt-search a').first()).toBeVisible();
            await header.getByRole('button', {name:'Cerrar búsqueda'}).click();
            await expect(header.getByRole('button', {name:'Buscar', exact:true})).toBeVisible();
        }
        await header.getByRole('button', {name:'Abrir menú de perfil'}).click();
        await expect(page.locator('#account-popover')).toBeVisible();
        const account = await page.locator('#account-popover').boundingBox();
        expect(account.x).toBeGreaterThanOrEqual(0);
        expect(account.x+account.width).toBeLessThanOrEqual(width);
        await page.keyboard.press('Escape');
        await expect(page.locator('#account-popover')).toBeHidden();
        await header.getByRole('button', {name:/Notificaciones:/}).click();
        await expect(page.getByRole('dialog', {name:'Notificaciones',exact:true})).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog', {name:'Notificaciones',exact:true})).toBeHidden();
        await capture(page, 'header', width);
    }
});

test('Hábitos: fecha, consejo anclado y progreso integrado en cuatro anchos', async ({page}) => {
    await login(page);
    for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({width, height:1000});
        await page.goto('/habits');
        const guardian = page.locator(width < 1200 ? '.game-guardian--top' : '.game-guardian--rail');
        const navigation = page.getByRole('group', {name:'Navegación por fecha', exact:true});
        await expect(navigation).toBeVisible();
        await expect(navigation.locator('time')).toHaveCSS('font-weight','700');
        await expect(navigation.getByRole('button')).toHaveCount(2);
        const today = await navigation.locator('time').getAttribute('datetime');
        await expect(guardian).toBeVisible();
        await expect(page.locator('.game-guardian--habits:visible')).toHaveCount(1);
        const more = guardian.getByRole('button', {name:'Otro consejo'});
        await expect(guardian.locator('.game-guardian__heading').getByRole('button', {name:'Otro consejo'})).toBeVisible();
        const heading = await guardian.locator('.game-guardian__heading').boundingBox();
        const button = await more.boundingBox();
        expect(button.y).toBeLessThan(heading.y+heading.height);
        expect(button.width).toBe(44);
        expect(button.height).toBe(44);
        const progress = await guardian.locator('.game-guardian__progress').boundingBox();
        const card = await guardian.boundingBox();
        expect(Math.abs(progress.width-card.width)).toBeLessThan(3);
        await expect(guardian.locator('.game-guardian__progress')).not.toHaveCSS('background-color','rgb(255, 255, 255)');
        await more.click();
        await expect(more).toBeEnabled();
        await expect(guardian).toBeVisible();
        await capture(page, 'habits-integrated', width);
        const axe = await new AxeBuilder({page}).include('.habit-date-navigation').include('.game-guardian--habits').analyze();
        expect(axe.violations).toEqual([]);
        await navigation.getByRole('button', {name:'Día anterior'}).click();
        await expect(navigation.locator('time')).toHaveAttribute('datetime',fixture.yesterday);
        await expect(page).toHaveURL(new RegExp(`date=${fixture.yesterday}`));
        await expect(guardian.getByRole('button', {name:'Otro consejo'})).toHaveCount(0);
        await expect(guardian.locator('.game-guardian__cue')).toHaveCount(0);
        await expect(guardian.locator('.game-guardian__bubble')).toContainText('hábitos completados el');
        await expect(guardian).toBeVisible();
        await capture(page, 'habits-other-date', width);
        await navigation.getByRole('button', {name:'Día siguiente'}).click();
        await expect(navigation.locator('time')).toHaveAttribute('datetime',today);
        await expect(more).toBeVisible();
        await expect(guardian.locator('.game-guardian__bubble')).not.toHaveText('');
        await navigation.getByRole('button', {name:'Día siguiente'}).click();
        await expect(navigation.locator('time')).toHaveAttribute('datetime',fixture.tomorrow);
        await expect(more).toHaveCount(0);
    }
    // Entrar directamente en otra fecha también debe recuperar consejos al volver a hoy.
    await page.goto(`/habits?date=${fixture.yesterday}`);
    await page.getByRole('button',{name:'Día siguiente',exact:true}).click();
    await expect(page.locator('.game-guardian--rail').getByRole('button',{name:'Otro consejo'})).toBeVisible();
});
