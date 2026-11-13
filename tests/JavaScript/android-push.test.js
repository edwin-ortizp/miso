import test from 'node:test';
import assert from 'node:assert/strict';
import {registerAndroid,androidPush} from '../../resources/js/android.js';
let account=null;
let remoteOrigin='https://miso.example';
let native={state:'ready',enabled:true,installation_id:'installation',secret:'test-secret',token:'test-token',binding:'binding',token_dirty:false};
let operations=[];
const windowEvents=new EventTarget();
globalThis.window=windowEvents;
globalThis.location={origin:'https://miso.example',href:'https://miso.example/'};
globalThis.CustomEvent=class extends Event {constructor(type,options={}){super(type,options);this.detail=options.detail;}};
globalThis.document={querySelector:selector=>selector.includes('miso-android')?{content:JSON.stringify({account,status:remoteOrigin+'/status',register:remoteOrigin+'/register',revoke:remoteOrigin+'/revoke'})}:{content:'csrf'},addEventListener:()=>{}};
window.MisoNative={postMessage:raw=>{
 const request=JSON.parse(raw);operations.push(request.type);
 if(request.id) queueMicrotask(()=>window.MisoNative.onmessage({data:JSON.stringify({v:1,id:request.id,...native})}));
}};
registerAndroid();
account='account';
function reset(){operations=[];remoteOrigin=location.origin;native={...native,state:'ready',enabled:true,token_dirty:false,binding:'binding'};}
test('unchanged token does not rewrite its registration',async()=>{
 reset();globalThis.fetch=async(url,options)=>{operations.push(url);assert.equal(options.credentials,'same-origin');return {ok:true,json:async()=>({configured:true,active:true,binding:'binding'})};};
 assert.equal(await androidPush('status'),'on');assert.equal(operations.filter(x=>x.endsWith('/register')).length,0);
});
test('rotated token is registered and its binding acknowledged',async()=>{
 reset();native.token_dirty=true;
 globalThis.fetch=async(url)=>{operations.push(url);return {ok:true,json:async()=>url.endsWith('/status')?{configured:true,active:true,binding:'binding'}:{binding:'binding'}};};
 assert.equal(await androidPush('status'),'on');assert.ok(operations.includes('pushBind'));assert.ok(operations.some(x=>x.endsWith('/register')));
});
test('disable clears local delivery even when the server is offline',async()=>{
 reset();globalThis.fetch=async()=>{throw new Error('offline');};
 await assert.rejects(androidPush('disable'));assert.ok(operations.includes('pushDisable'));
});
test('locally disabled devices revoke stale server registrations',async()=>{
 reset();native.enabled=false;
 globalThis.fetch=async(url)=>{operations.push(url);return {ok:true,json:async()=>({configured:true,active:true,binding:'binding'})};};
 assert.equal(await androidPush('status'),'off');assert.ok(operations.some(x=>x.endsWith('/revoke')));
});
test('cross-origin configuration never receives the installation secret',async()=>{
 reset();remoteOrigin='https://evil.example';let fetched=false;globalThis.fetch=async()=>{fetched=true;};
 await assert.rejects(androidPush('status'));assert.equal(fetched,false);
});
test('permission denial never registers a token on the server',async()=>{
 reset();native.state='denied';let fetched=false;globalThis.fetch=async()=>{fetched=true;};
 assert.equal(await androidPush('enable'),'denied');assert.equal(fetched,false);
});
