import test from 'node:test';
import assert from 'node:assert/strict';
import { registerTaskCalendar, readCalendarLayers } from '../../resources/js/task-calendar.js';

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

test('layer preferences validate stored values and tolerate inaccessible storage', () => {
    const original = globalThis.localStorage;
    try {
        for (const value of [null, '{', '{}', '{"tasks":false,"plans":true,"birthdays":"true"}']) {
            globalThis.localStorage = { getItem: () => value };
            assert.deepEqual(readCalendarLayers(), { tasks: true, plans: true, birthdays: true, meals: true });
        }
        globalThis.localStorage = { getItem: () => '{"tasks":false,"plans":true,"birthdays":false,"extra":42}' };
        assert.deepEqual(readCalendarLayers(), { tasks: false, plans: true, birthdays: false, meals: true });
        globalThis.localStorage = { getItem() { throw Error('blocked'); } };
        assert.deepEqual(readCalendarLayers(), { tasks: true, plans: true, birthdays: true, meals: true });
    } finally { globalThis.localStorage = original; }
});

test('layers filter mounted events without requests and persist even an empty selection', () => {
    const original = globalThis.localStorage;
    const values = new Map();
    try {
        globalThis.localStorage = { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) };
        const state = calendar({ events() { assert.fail('No request expected'); } });
        const events = ['tasks', 'plans', 'birthdays'].map(type => ({ extendedProps: { type }, setProp(key, value) { this[key] = value; } }));
        state.calendar = { batchRendering: fn => fn(), getEvents: () => events };
        state.fitCalendar = () => {};
        for (const type of ['tasks', 'plans', 'birthdays']) state.toggleLayer(type);
        assert.deepEqual(events.map(event => event.display), ['none', 'none', 'none']);
        assert.deepEqual(readCalendarLayers(), { tasks: false, plans: false, birthdays: false, meals: true });
        state.toggleLayer('meals');
        assert.equal(readCalendarLayers().meals, false);
        state.toggleLayer('plans');
        assert.deepEqual(events.map(event => event.display), ['none', 'auto', 'none']);
        assert.equal(state.quickOpen, false);
        globalThis.localStorage.setItem = () => { throw Error('blocked'); };
        state.toggleLayer('tasks');
        assert.equal(events[0].display, 'auto');
    } finally { globalThis.localStorage = original; }
});

test('only task events can be persisted and failures revert the drag', async () => {
    const calls = [];
    const state = calendar({ moveTask: async (...args) => calls.push(args) });
    let reverted = 0;
    for (const type of ['plans', 'birthdays', 'meals', 'tasks']) {
        await state.persistEvent({ event: { id: type, startStr: '2026-10-05', endStr: '', allDay: true, extendedProps: { type } }, revert: () => reverted++ });
    }
    assert.equal(reverted, 3);
    assert.deepEqual(calls, [['tasks', '2026-10-05', null, true]]);
    await calendar({ moveTask: async () => { throw Error('offline'); } }).persistEvent({ event: { extendedProps: { type: 'tasks' } }, revert: () => reverted++ });
    assert.equal(reverted, 4);
});

test('clicks route new layers to detail and keep task quick view', () => {
    const original = globalThis.window;
    const urls = [];
    try {
        globalThis.window = { location: { assign: url => urls.push(url) } };
        const state = calendar({});
        let opened = false;
        state.openQuick = () => { opened = true; };
        state.clickEvent({ event: { extendedProps: { type: 'tasks' } } });
        assert.equal(opened, true);
        for (const type of ['plans', 'birthdays']) state.clickEvent({ event: { extendedProps: { type, detailUrl: `/${type}/id` } } });
        assert.deepEqual(urls, ['/plans/id', '/birthdays/id']);
    } finally { globalThis.window = original; }
});

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
