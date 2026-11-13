import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {resolve,join} from 'node:path';
import {readFile,mkdir,writeFile} from 'node:fs/promises';
import {createServer} from 'node:http';
import {seed,togglePin,pinTarget,pinnedTargets} from '../scripts/domain.js';
const require=createRequire(resolve('../mockup-system/package.json'));
const {chromium}=require('playwright');
const {default:AxeBuilder}=createRequire(resolve('package.json'))('@axe-core/playwright');
const dist=resolve(process.env.MISO_MOCKUP_DIST||'docs/mockup-system/dist');
const artifacts=join(dist,'learning-review','shortcuts');await mkdir(artifacts,{recursive:true});
// Pinning is a manual preference, never a change to the academic structure or state.
const sample=seed(),original=structuredClone({learnings:sample.learnings,nodes:sample.nodes,tasks:sample.tasks,slots:sample.slots});
assert.equal(togglePin(sample,'node:degree-period'),false);
assert.equal(togglePin(sample,'node:degree-period'),true);
assert.equal(sample.pins.at(-1),'node:degree-period');
assert.equal(new Set(sample.pins).size,sample.pins.length);
assert.equal(togglePin(sample,'node:missing'),false);
assert.deepEqual({learnings:sample.learnings,nodes:sample.nodes,tasks:sample.tasks,slots:sample.slots},original);
sample.nodes.find(n=>n.id==='degree-period').status='Completado';assert(sample.pins.includes('node:degree-period'));
sample.learnings.find(l=>l.id==='degree').title='Ingeniería renombrada';assert.match(pinTarget(sample,'node:degree-period').origin,/Ingeniería renombrada/);
for(const kind of ['period','subject','module','topic','stage','course']){const id='example-'+kind;sample.nodes.push({id,learning:'english',parent:'',kind,title:kind,status:'Pendiente'});assert.equal(togglePin(sample,'node:'+id),true);}
assert.equal(pinnedTargets({...sample,pins:['node:missing','formation:missing']}).length,0);
assert.deepEqual(seed({empty:true}).pins,[]);
const server=createServer(async(req,res)=>{res.setHeader('Content-Type','text/html; charset=utf-8');res.end(await readFile(join(dist,'learning.html')));});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const browser=await chromium.launch({headless:true}),errors=[],network=[],checks=[];
try{
 const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),page=await context.newPage();page.setDefaultTimeout(15000);
 page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(!r.url().startsWith('http://127.0.0.1:')&&!r.url().startsWith('data:'))network.push(r.url());});
 await page.goto(`http://127.0.0.1:${server.address().port}/learning.html`);const host=page.locator('#screen-host');
 const route=async(v,formation='',unit='')=>{await page.evaluate(hash=>location.hash=hash,'/learning-'+v+(formation?'?formation='+formation+'&unit='+unit:''));await page.waitForTimeout(120);assert.equal(await page.locator('#fatal-error').isVisible(),false);};
 const scenario=async id=>{const select=page.locator('#andamio-escenario');if(!await select.isVisible())await page.locator('#andamio-toggle').click();await select.selectOption(id);if(await page.locator('#andamio-toggle').isVisible()&&await page.locator('#andamio-toggle').getAttribute('aria-expanded')==='true')await page.locator('#andamio-toggle').click();await page.waitForTimeout(100);};
 const pins=()=>host.locator('[data-shortcut-key]');const keys=()=>pins().evaluateAll(nodes=>nodes.map(n=>n.dataset.shortcutKey));
 const rowPin=async(title,action)=>{const row=host.locator('tr').filter({has:page.getByText(title,{exact:true})});await row.locator('[data-lt-toggle]:visible').first().click();await host.locator(`[data-learning-portal]:visible [data-lt-action="${action}"]`).click();};
 assert.deepEqual(await keys(),['node:degree-period','formation:english','formation:guitar']);
 // One click enters the semester, bypassing the career and preserving contextual actions.
 await host.locator('[data-shortcut-key="node:degree-period"]').click();await page.waitForTimeout(100);
 assert.equal(await host.locator('[data-page-title]').innerText(),'Semestre 1');assert.match(await host.locator('[data-breadcrumbs]').innerText(),/Ingeniería de Sistemas/);assert.match(await host.locator('.lt-fab-split__main').innerText(),/Agregar materia/);
 const pin=host.locator('[data-pin-current]');assert.equal(await pin.getAttribute('aria-pressed'),'true');await pin.click();assert.equal(await pin.getAttribute('aria-pressed'),'false');assert(await pin.evaluate(el=>document.activeElement===el));assert.equal(await host.locator('[data-shortcut-key="node:degree-period"]').count(),0);assert.equal(await host.locator('[data-page-title]').innerText(),'Semestre 1');
 await pin.click();assert.deepEqual(await keys(),['formation:english','formation:guitar','node:degree-period']);
 for(const [key,title]of [['formation:english','Ruta de inglés B1'],['formation:guitar','Clases de guitarra']]){await host.locator(`[data-shortcut-key="${key}"]`).click();await page.waitForTimeout(100);assert.equal(await host.locator('[data-page-title]').innerText(),title);}
 // Both row menus expose the same manual action; repeated names retain their origin.
 await route('list');await rowPin('Scrum: fundamentos y práctica','pin-formation');assert.equal(await pins().count(),4);
 await route('detail','master');await rowPin('Semestre 1','pin-node');assert.equal(await pins().count(),5);
 assert.match(await host.locator('[data-shortcut-key="node:degree-period"]').innerText(),/Ingeniería de Sistemas/);assert.match(await host.locator('[data-shortcut-key="node:semester"]').innerText(),/Maestría en gestión de proyectos/);
 // Updating a status never removes a shortcut.
 await host.locator('[data-shortcut-key="node:semester"]').click();await page.waitForTimeout(100);await host.locator('[data-learning-action="edit-learning"]').click();await host.locator('[data-form="node"] [name="status"]').selectOption('Completado');await host.locator('[data-lt-action="node-save"]').click();assert.equal(await host.locator('[data-shortcut-key="node:semester"]').count(),1);
 // Renaming uses live record labels, without changing the stored target or ordering.
 await host.locator('[data-shortcut-key="formation:guitar"]').click();await page.waitForTimeout(100);await host.locator('[data-learning-action="edit-learning"]').click();await host.locator('[data-form="learning"] [name="title"]').fill('Clases de guitarra · acordes y práctica de canciones');await host.locator('[data-lt-action="learning-save"]').click();assert.match(await host.locator('[data-shortcut-key="formation:guitar"]').innerText(),/acordes y práctica/);
 const before=await keys();await host.locator('.lt-sidebar [data-module="tasks"]').click();await page.waitForTimeout(120);assert.equal(await host.locator('[data-shortcuts-panel]').isVisible(),false);await host.locator('.lt-sidebar [data-module="learning"]').click();await page.waitForTimeout(120);assert.deepEqual(await keys(),before);
 // Removing a pin from the index does not remove the underlying formation.
 await host.locator('[data-learning-action="unpin"][data-id="formation:scrum"]').click();assert.equal(await host.locator('[data-shortcut-key="formation:scrum"]').count(),0);assert.match(await host.locator('tbody').innerText(),/Scrum: fundamentos y práctica/);assert(await pins().first().evaluate(el=>document.activeElement===el));
 await host.locator('[data-learning-action="unpin"][data-id="formation:english"]').click();await host.locator('[data-learning-action="unpin"][data-id="formation:guitar"]').click();await host.locator('[data-learning-action="unpin"][data-id="node:degree-period"]').click();await host.locator('[data-learning-action="unpin"][data-id="node:semester"]').click();assert.match(await host.locator('[data-shortcuts-list]').innerText(),/Todavía no tienes accesos/);assert.match(await host.locator('tbody').innerText(),/Maestría/);
 await page.evaluate(()=>document.querySelector('#reset-data').click());await page.waitForTimeout(100);assert.deepEqual(await keys(),['node:degree-period','formation:english','formation:guitar']);
 // Responsive layout: direct links before the table below 1200, guardian secondary.
 for(const [profile,width,height]of [['desktop',1440,1000],['tablet',1199,900],['mobile',375,812]]){
  await page.setViewportSize({width,height});await route('list');const main=await host.locator('.lt-learning-main').boundingBox(),shortcuts=await host.locator('[data-shortcuts-panel]').boundingBox(),guardian=await host.locator('[data-formation-guardian]').boundingBox();
  if(width>=1200){assert(shortcuts.x>=main.x+main.width);assert(guardian.x>=main.x+main.width);}else{assert(shortcuts.y+shortcuts.height<=main.y+1);assert(guardian.y>=main.y+main.height-1);}
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
  await page.screenshot({path:join(artifacts,profile+'-index.png'),fullPage:true});
  const axe=await new AxeBuilder({page}).include('.lt-learning').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();assert.deepEqual(axe.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})),[]);checks.push({profile,passed:true});
  const link=host.locator('[data-shortcut-key="node:degree-period"]');await link.focus();await page.keyboard.press('Enter');await page.waitForTimeout(120);assert.equal(await host.locator('[data-page-title]').innerText(),'Semestre 1');await page.screenshot({path:join(artifacts,profile+'-semester.png'),fullPage:true});
 }
 // No team preserves manual shortcuts and the independent favorite companion.
 await scenario('learning-no-team');await route('list');assert.equal(await host.locator('[data-formation-guardian] [data-guardian]:visible').count(),0);assert.equal(await host.locator('[data-rail-no-team]').isVisible(),true);assert.equal(await pins().count(),3);assert.equal(await host.locator('[data-favorite-dock]').isVisible(),true);
 await scenario('learning-empty');await route('list');assert.equal(await host.locator('[data-shortcuts-panel]').isVisible(),false);assert.equal(await host.locator('[data-empty-guide]').isVisible(),true);assert.equal(await pins().count(),0);
 // A new active course is not auto-pinned. Pinning is always the user's choice.
 await page.setViewportSize({width:1440,height:1000});await host.locator('.lt-fab-split__main').click();const form=host.locator('[data-form="learning"]');await form.locator('[name="type"]').selectOption('Curso');await form.locator('[name="title"]').fill('Curso nuevo sin fijar');await host.locator('[data-lt-action="learning-save"]').click();assert.equal(await pins().count(),0);assert.match(await host.locator('[data-shortcuts-list]').innerText(),/Fijar en accesos directos/);
 await page.reload();await page.waitForTimeout(100);assert.deepEqual(await keys(),['node:degree-period','formation:english','formation:guitar']);
 assert.deepEqual(errors,[]);assert.deepEqual(network,[]);
 await writeFile(join(artifacts,'results.json'),JSON.stringify({passed:true,checks,errors,externalRequests:network,behaviors:['manual pins','all hierarchy types','direct navigation','duplicate names','live labels','status independence','remove and reorder','reset and scenario isolation','keyboard','responsive order','Axe']},null,2));
 console.log('PASS: manual shortcuts, direct semester/course entry, module rail, status independence, desktop/tablet/mobile, keyboard, Axe and reset.');
}catch(error){const page=browser.contexts()[0]?.pages()[0];if(page)await page.screenshot({path:join(artifacts,'failure.png'),fullPage:true});throw error;}finally{await browser.close();await new Promise(r=>server.close(r));}
