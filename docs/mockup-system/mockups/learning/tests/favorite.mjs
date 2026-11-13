import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {resolve,join} from 'node:path';
import {readFile,mkdir,writeFile} from 'node:fs/promises';
import {createServer} from 'node:http';
const require=createRequire(resolve('../mockup-system/package.json'));
const {chromium}=require('playwright');
const appRequire=createRequire(resolve('package.json'));const {default:AxeBuilder}=appRequire('@axe-core/playwright');
const dist=resolve(process.env.MISO_MOCKUP_DIST||'docs/mockup-system/dist'),artifacts=resolve('docs/mockup-system/dist/favorite-review');await mkdir(artifacts,{recursive:true});
const server=createServer(async(req,res)=>{try{res.setHeader('Content-Type','text/html; charset=utf-8');res.end(await readFile(join(dist,'learning.html')));}catch{res.writeHead(404).end();}});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const browser=await chromium.launch({headless:true}),results=[];
try{
 for(const [profile,width,height] of [['desktop',1440,1000],['tablet',834,1112],['mobile',390,844]]){
  const context=await browser.newContext({viewport:{width,height},reducedMotion:'reduce'}),page=await context.newPage(),errors=[],network=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(!r.url().startsWith('http://127.0.0.1:')&&!r.url().startsWith('data:'))network.push(r.url());});
  await page.goto(`http://127.0.0.1:${server.address().port}/learning.html`);
  const host=page.locator('#screen-host'),dock=host.locator('[data-favorite-dock]');
  const route=async view=>{await page.evaluate(v=>location.hash='/learning-'+v,view);await page.waitForTimeout(200);assert.equal(await page.locator('#fatal-error').isVisible(),false);};
  const action=name=>host.locator(`[data-favorite-action="${name}"]:visible`);
  const scenario=async id=>{const select=page.locator('#andamio-escenario');if(!await select.isVisible())await page.locator('#andamio-toggle').click();await select.selectOption(id);if(await page.locator('#andamio-toggle').isVisible()&&await page.locator('#andamio-toggle').getAttribute('aria-expanded')==='true')await page.locator('#andamio-toggle').click();await page.waitForTimeout(200);};
  const avatar=dock.locator('[data-favorite-action=open]');
  const openProfile=async()=>{await avatar.click();assert.equal(await host.locator('[data-favorite-bubble]').isVisible(),true);await action('profile').click();};
  assert.match(await avatar.getAttribute('aria-label'),/Pikachu/);await page.screenshot({path:join(artifacts,`${profile}-presence.png`),fullPage:true});
  assert.equal(await avatar.innerText(),'');await avatar.click();assert.match(await host.locator('[data-favorite-cry]').innerText(),/Pika/);
  await page.screenshot({path:join(artifacts,`${profile}-bubble.png`),fullPage:true});
  let bubbleAxe=await new AxeBuilder({page}).include('[data-favorite-dock]').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();assert.deepEqual(bubbleAxe.violations.map(x=>x.id),[]);
  const bubbleBox=await host.locator('[data-favorite-bubble]').boundingBox(),avatarBox=await avatar.boundingBox();assert(bubbleBox.x>=0&&bubbleBox.x+bubbleBox.width<=width&&bubbleBox.y>=0);assert(avatarBox.x>=0&&avatarBox.y+avatarBox.height<=height);
  const fabBox=await host.locator('[data-learning-fab] .lt-fab:visible').first().boundingBox();if(fabBox)assert(avatarBox.x+avatarBox.width<=fabBox.x||avatarBox.y+avatarBox.height<=fabBox.y);
  const navBox=await host.locator('.lt-bottom:visible').boundingBox().catch(()=>null);if(navBox)assert(avatarBox.y+avatarBox.height<=navBox.y);
  await page.keyboard.press('Escape');assert.equal(await host.locator('[data-favorite-bubble]').isVisible(),false);assert(await avatar.evaluate(el=>el===document.activeElement));
  await openProfile();await action('choose').click();
  assert.equal(await host.locator('#favorite-chooser [aria-pressed=true]').innerText().then(x=>x.includes('Pikachu')),true);
  const choices=host.locator('#favorite-chooser');await page.screenshot({path:join(artifacts,`${profile}-chooser.png`),fullPage:true});
  let axe=await new AxeBuilder({page}).include('#favorite-chooser').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();assert.deepEqual(axe.violations.map(x=>x.id),[]);
  await page.keyboard.press('Shift+Tab');assert(await choices.evaluate(el=>el.contains(document.activeElement)));
  await page.keyboard.press('Escape');assert.equal(await choices.isVisible(),false);assert.equal(await host.locator('#favorite-profile').isVisible(),true);assert(await action('choose').evaluate(el=>el===document.activeElement));
  await action('choose').click();await host.locator('[data-species="133"]').click();assert.match(await avatar.getAttribute('aria-label'),/Eevee/);
  assert(await avatar.evaluate(el=>el===document.activeElement));
  for(const view of ['calendar','tasks','goals','habits','focus','list']){
   await route(view);if(view==='tasks'){assert.equal(await dock.isVisible(),false);assert.equal(await host.locator('[data-learning-fab] .lt-task-create--pokemon').count(),0);assert.equal(await host.locator('.lt-task-create--pokemon .lt-fab-split__main img').count(),1);continue;}const visible=host.locator('[data-favorite-action="open"]:visible');assert.equal(await visible.count(),1);assert.match(await visible.getAttribute('aria-label'),/Eevee/);
   const guardianText=await host.locator('[data-guardian]').allTextContents();assert(guardianText.every(x=>!x.includes('Eevee')));
   if(view==='focus'){await host.locator('[data-learning-action=focus-start]').click();assert.match(await host.locator('[data-learning-action=focus-start]').innerText(),/Pausar/);await host.locator('[data-learning-action=focus-start]').click();assert.equal(await dock.isVisible(),true);await page.screenshot({path:join(artifacts,`${profile}-focus.png`),fullPage:true});}
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
  }
  await openProfile();await action('hide').click();assert.equal(await dock.isVisible(),false);assert(await host.locator('.lt-profile').evaluate(el=>el===document.activeElement));
  await route('goals');assert.equal(await dock.isVisible(),false);await host.locator('.lt-profile').click();assert.equal(await action('restore').innerText(),'Mostrar compañero');await action('restore').click();assert.match(await avatar.getAttribute('aria-label'),/Eevee/);
  await page.evaluate(()=>document.querySelector('#reset-data').click());await page.waitForTimeout(200);assert.match(await avatar.getAttribute('aria-label'),/Pikachu/);
  await scenario('learning-no-favorite');assert.match(await avatar.getAttribute('aria-label'),/Elegir compañero/);await openProfile();await host.locator('[data-species="63"]').click();assert.match(await avatar.getAttribute('aria-label'),/Abra/);
  await scenario('learning-no-collection');await openProfile();assert.equal(await host.locator('[data-species]').count(),0);assert.equal(await host.locator('[data-favorite-empty]').isVisible(),true);await page.screenshot({path:join(artifacts,`${profile}-empty.png`),fullPage:true});await page.keyboard.press('Escape');
  await scenario('learning-no-team');assert.match(await avatar.getAttribute('aria-label'),/Pikachu/);assert.equal(await host.locator('[data-guardian]:visible').count(),0);
  await scenario('learning-demo');await route('goals');await page.evaluate(()=>window.scrollTo(0,document.body.scrollHeight));const box=await dock.boundingBox();assert(box&&box.y>=0&&box.y<height,'Companion remains visible while scrolling');
  axe=await new AxeBuilder({page}).include('#screen-host').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();assert.deepEqual(axe.violations.map(x=>x.id),[]);
  assert.deepEqual(errors,[]);assert.deepEqual(network,[]);results.push({profile,passed:true,errors,externalRequests:network});await context.close();
 }
 await writeFile(join(artifacts,'results.json'),JSON.stringify(results,null,2));console.log('PASS: favorite selection, persistence, focus, profile, reset, scenarios, keyboard, axe, responsive and offline.');
}catch(error){const page=browser.contexts().at(-1)?.pages()[0];if(page)await page.screenshot({path:join(artifacts,'failure.png'),fullPage:true});throw error;}finally{await browser.close();await new Promise(r=>server.close(r));}
