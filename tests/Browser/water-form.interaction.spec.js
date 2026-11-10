import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, closeSync, openSync } from 'node:fs';
import path from 'node:path';
let server;
let serverEnv;
test.describe.configure({ mode: 'serial' });
test.use({ actionTimeout: 15000, hasTouch: true });
test.setTimeout(180000);
test.beforeAll(async () => {
    test.setTimeout(120_000);
    const directory = path.resolve('storage/framework/testing');
    mkdirSync(directory, { recursive: true });
    const database = path.join(directory, 'water-form-browser.sqlite');
    closeSync(openSync(database, 'a'));
    const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', DEBUGBAR_ENABLED: 'false', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false', APP_CONFIG_CACHE: path.join(directory, 'unused-water-form-config.php') };
    serverEnv = env;
    expect(execFileSync('php', ['tests/Browser/support/water-form-fixture.php'], { env, encoding: 'utf8' })).toContain('Water form fixture ready.');
    server = spawn('php', ['-S', '127.0.0.1:8031', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env, stdio: 'pipe', windowsHide: true });
    let serverOutput = '';
    server.stdout.on('data', data => { serverOutput += data; });
    server.stderr.on('data', data => { serverOutput += data; });
    for (let attempt = 0; attempt < 60; attempt++) {
        try { if ((await fetch('http://127.0.0.1:8031/login')).ok) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw new Error(`Isolated water form server did not start: ${serverOutput}`);
});
test.afterAll(() => { server?.kill(); });


for (const width of [375, 768, 1280]) {
 test(`water protected form ${width}`, async ({page}) => {
  expect(execFileSync('php', ['tests/Browser/support/water-form-fixture.php'], { env: serverEnv, encoding: 'utf8' })).toContain('Water form fixture ready.');
  const errors=[]; page.on('pageerror', e=>errors.push(e.message));
  await page.setViewportSize({width,height:900});
  await page.goto('http://127.0.0.1:8031/login');
  await page.getByLabel('Correo electrónico').fill('water-form@example.test');
  await page.getByLabel('Contraseña',{exact:true}).fill('browser-test-password');
  await page.getByRole('button',{name:'Iniciar Sesión'}).click();
  await page.waitForURL(url=>!url.pathname.includes('/login'));
  await page.goto('http://127.0.0.1:8031/water/daily');
  const open=()=>page.getByRole('button',{name:'Registrar',exact:true}).click();
  const d=page.locator('#water-log-form');
  const input=d.getByRole('combobox');
  const button=name=>d.getByRole('button',{name,exact:true});
  const confirm=async()=> {
   await expect(d.getByText('¿Descartar los cambios?')).toBeVisible();
   await expect(button('Seguir editando')).toBeFocused();
  };
  await open(); await input.fill('no-existe');
  await expect(d.getByText('Sin resultados',{exact:true})).toBeVisible();
  await input.press('Escape'); await expect(d).toBeVisible();
  await button('Cancelar').click(); await expect(d).toHaveCount(0);
  await open(); await input.fill('TE DE PRUEBA'); await input.press('ArrowDown'); await input.press('Enter');
  await expect(input).toHaveValue('🍵 Té de prueba (x0.9)');
  for(const close of [()=>button('Cancelar').click(),()=>button('Cerrar').click(),()=>input.press('Escape'),()=>page.locator('.md-dialog-scrim').click({position:{x:2,y:2},force:true})]) {
   await close(); await confirm();
   expect((await new AxeBuilder({page}).include('#water-log-form').analyze()).violations).toEqual([]);
   await button('Seguir editando').click();
   await expect(input).toHaveValue('🍵 Té de prueba (x0.9)');
  }
  await button('Cancelar').click(); await button('Descartar cambios').click(); await expect(d).toHaveCount(0);
  await open(); await expect(input).toHaveValue('');
  const amount=d.getByLabel('Cantidad (ml)',{exact:true});
  const time=d.getByLabel('Hora',{exact:true});
  const initialTime=await time.inputValue();
  await time.fill(initialTime === '10:30' ? '11:30' : '10:30'); await button('Cancelar').click(); await confirm();
  await page.keyboard.press('Escape'); await expect(input).toBeVisible();
  await time.fill(initialTime);
  await amount.fill('300'); await amount.fill('250'); await button('Cancelar').click(); await expect(d).toHaveCount(0);
  await open(); await button('Guardar').click(); await expect(d.locator('[aria-invalid=true]')).not.toHaveCount(0);
  await input.fill('TE DE PRUEBA');
  const option=d.getByRole('option',{name:'🍵 Té de prueba (x0.9)',exact:true});
  if(width===375) await option.tap(); else await option.click();
  await button('330 ml').click(); await expect(amount).toHaveValue('330');
  expect((await new AxeBuilder({page}).include('#water-log-form').analyze()).violations).toEqual([]);
  const bounds=await d.boundingBox();
  expect(bounds.x).toBeGreaterThanOrEqual(0); expect(bounds.x+bounds.width).toBeLessThanOrEqual(width+1);
  await expect(button('Guardar')).toBeInViewport();
  await page.screenshot({path:`storage/framework/testing/water-form-${width}.png`});
  await button('Guardar').click(); await expect(d).toHaveCount(0);
  const edit = page.getByRole('button',{name:'Editar',exact:true}).first();
  if (!await edit.isVisible()) {
   await page.getByRole('button',{name:'Más acciones del registro de Té de prueba',exact:true}).first().click();
   await page.getByRole('menuitem',{name:'Editar',exact:true}).click();
  } else await edit.click();
  await expect(input).toHaveValue('🍵 Té de prueba (x0.9)'); await expect(amount).toHaveValue('330');
  await amount.fill('350'); await button('Cancelar').click(); await confirm(); await button('Seguir editando').click();
  await button('Actualizar').click(); await expect(d).toHaveCount(0); expect(errors).toEqual([]);
 });
}
