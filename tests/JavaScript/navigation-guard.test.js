import test from 'node:test';
import assert from 'node:assert/strict';
import {formSnapshot,addSurface,guarded,handleBack,confirmDiscard} from '../../resources/js/navigation-guard.js';
const events = new EventTarget();
globalThis.window = events;
globalThis.requestAnimationFrame = callback => callback();
globalThis.CustomEvent = class extends Event { constructor(type,options={}) {super(type,options);this.detail=options.detail;} };
const field = (name,value,type='text') => ({name,value,type,attributes:[]});
test('snapshot detects Unicode, selections, files and returns to clean state', () => {
 const fields=[field('title','Diario 🌱'),{...field('done','', 'checkbox'),checked:false},{...field('file','', 'file'),files:[]}];
 const original=formSnapshot(fields); fields[0].value+=' hoy'; assert.notEqual(formSnapshot(fields),original);
 fields[0].value='Diario 🌱'; assert.equal(formSnapshot(fields),original);
 fields[1].checked=true;assert.notEqual(formSnapshot(fields),original);
 fields[1].checked=false; fields[2].files=[{name:'foto.jpg',size:30,lastModified:123}];assert.notEqual(formSnapshot(fields),original);
});
test('back consumes only the topmost surface, then returns to normal navigation', async () => {
 const el={isConnected:true,getClientRects:()=>[{}]};const calls=[];
 const first=addSurface(el,()=>calls.push('dialog'));const second=addSurface(el,()=>calls.push('menu'));
 assert.equal(guarded(),true);assert.equal(await handleBack(),true);assert.deepEqual(calls,['menu']);
 second();await handleBack();assert.deepEqual(calls,['menu','dialog']);first();assert.equal(guarded(),false);
 globalThis.document={};assert.equal(await handleBack(),false);
});
test('discard confirmation fails closed without a mounted surface and ignores repeated requests', async () => {
 assert.equal(await confirmDiscard(),false);
 let resolve;const listener=event=>{event.preventDefault();resolve=event.detail.resolve;};events.addEventListener('miso-discard-request',listener);
 const first=confirmDiscard();assert.equal(await confirmDiscard(),false);resolve(true);assert.equal(await first,true);
 events.removeEventListener('miso-discard-request',listener);
});
