import test from 'node:test';
import assert from 'node:assert/strict';
import { registerTaskCalendar } from '../../resources/js/task-calendar.js';

function calendar(wire) {
    let factory;
    registerTaskCalendar({ data: (_, value) => { factory = value; } });
    return Object.assign(factory(wire), {
        selected: { id: 'task', status: { id: 'draft' } },
        quickOpen: true,
        $nextTick: async () => {},
        $refs: { statusTrigger: { focus() {} } },
    });
}

test('failed status requests preserve the popover and allow retry', async () => {
    let focused = false;
    const state = calendar({ changeStatus: async () => { throw Error('offline'); } });
    state.$refs.statusTrigger.focus = () => { focused = !state.busy; };
    await state.changeStatus('wip');
    assert.equal(state.quickOpen, true);
    assert.equal(state.busy, false);
    assert.ok(state.statusError);
    assert.equal(focused, true);
});

test('duplicate and current-state requests are ignored; success closes the popover', async () => {
    let calls = 0;
    let resolve;
    const state = calendar({ changeStatus: () => { calls++; return new Promise(done => { resolve = done; }); } });
    await state.changeStatus('draft');
    assert.equal(calls, 0);
    const request = state.changeStatus('wip');
    await state.changeStatus('done');
    assert.equal(calls, 1);
    resolve();
    await request;
    assert.equal(state.quickOpen, false);
    assert.equal(state.busy, false);
});

for (const view of ['timeGridDay', 'timeGridWeek', 'dayGridMonth', 'listWeek']) {
    for (const [hours, minutes, expected] of [[14, 45, 855], [0, 10, 0], [23, 59, 1409]]) {
        test(`Ahora navigates and scrolls from ${view} at ${hours}:${minutes}`, () => {
            const originalRequest = globalThis.requestAnimationFrame;
            const originalCancel = globalThis.cancelAnimationFrame;
            let frame;
            globalThis.requestAnimationFrame = callback => { frame = callback; return 1; };
            globalThis.cancelAnimationFrame = () => {};
            try {
                const state = calendar({});
                const calls = [];
                const now = new Date(2026, 9, 22, hours, minutes);
                state.calendar = {
                    view: { type: view },
                    gotoDate: date => calls.push(['date', date]),
                    changeView: (type, date) => calls.push([type, date]),
                    scrollToTime: time => calls.push(['scroll', time.minutes]),
                };
                state.goToNow(now);
                assert.deepEqual(calls, [[view.startsWith('timeGrid') ? 'date' : 'timeGridDay', now]]);
                frame();
                assert.deepEqual(calls[1], ['scroll', expected]);
            } finally {
                globalThis.requestAnimationFrame = originalRequest;
                globalThis.cancelAnimationFrame = originalCancel;
            }
        });
    }
}
