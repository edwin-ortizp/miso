import test from 'node:test';
import assert from 'node:assert/strict';
import { zonedClock, timelineGroups, createPanelRefresh, PANEL_REFRESH_INTERVAL } from '../../resources/js/wall-panel.js';
test('clock follows configured timezone at midnight', () => {
 assert.deepEqual(zonedClock(new Date('2026-10-24T04:59:00Z'), 'America/Bogota'), {clock:'23:59',date:'2026-10-23',minute:1439});
 assert.deepEqual(zonedClock(new Date('2026-10-24T05:00:00Z'), 'America/Bogota'), {clock:'00:00',date:'2026-10-24',minute:0});
});
test('timeline groups nearby times and excludes items outside its eight hour window', () => {
 const result=timelineGroups([{title:'A',time:'09:00'},{title:'B',time:'09:30'},{title:'C',time:'10:30'},{title:'D',time:'20:00'}],480);
 assert.deepEqual(result.map(group=>group.map(item=>item.title)),[['A','B'],['C']]);
});

test('refresh is due after five minutes and local writes reset the deadline', async () => {
 let now = 1000000, calls = 0;
 const state = createPanelRefresh({now: () => now, request: async () => { calls++; }});
 state.updated('initial');
 now += PANEL_REFRESH_INTERVAL - 1; assert.equal(state.due(), false);
 now++; assert.equal(state.due(), true);
 await state.refresh(); assert.equal(calls, 1); assert.equal(state.due(), false);
 now += 240000; state.updated('write'); now += 60000; assert.equal(state.due(), false);
});
test('concurrent refreshes are ignored and failure keeps the last success until retry', async () => {
 let now = 1000000, rejectRequest, calls = 0;
 const state = createPanelRefresh({now: () => now, request: () => { calls++; return new Promise((resolve, reject) => {rejectRequest=reject;}); }});
 state.updated('initial'); const lastSuccess=state.lastSuccessAt; now += PANEL_REFRESH_INTERVAL;
 const pending=state.refresh(); await state.refresh(); assert.equal(calls, 1); assert.equal(state.refreshing, true);
 rejectRequest(new Error('offline')); await pending;
 assert.equal(state.lastSuccessAt, lastSuccess); assert.equal(state.refreshError, 'No se pudo actualizar');
 assert.equal(state.refreshing, false); assert.equal(state.due(), false);
 now += PANEL_REFRESH_INTERVAL; assert.equal(state.due(), true);
 state.updated('recovered'); assert.equal(state.refreshError, '');
});
test('a failed initial refresh also waits five minutes before automatic retry', async () => {
 let now=1000000;
 const state=createPanelRefresh({now:()=>now,request:async()=>{throw Error('offline');}});
 assert.equal(state.due(),true); await state.refresh(); assert.equal(state.due(),false);
 now+=PANEL_REFRESH_INTERVAL; assert.equal(state.due(),true);
});
