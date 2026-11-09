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

test('task clicks open the shared editor and other layers keep their detail links', () => {
    const original = globalThis.window;
    const urls = [];
    try {
        globalThis.window = { location: { assign: url => urls.push(url) } };
        const state = calendar({});
        const dispatched = [];
        state.$dispatch = (...args) => dispatched.push(args);
        state.clickEvent({ event: { id: 'task-1', extendedProps: { type: 'tasks' } } });
        assert.deepEqual(dispatched, [['task-editor', { action: 'openForm', id: 'task-1' }]]);
        for (const type of ['plans', 'birthdays', 'meals']) state.clickEvent({ event: { extendedProps: { type, detailUrl: `/${type}/id` } } });
        assert.deepEqual(urls, ['/plans/id', '/birthdays/id', '/meals/id']);
    } finally { globalThis.window = original; }
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

test('layer draft applies only on confirmation and can represent no visible layers', () => {
    const original = globalThis.localStorage;
    const writes = [];
    try {
        globalThis.localStorage = { getItem: () => null, setItem: (_, value) => writes.push(value) };
        const state = calendar({});
        state.$refs = { filterTrigger: { focus() {} } };
        state.$nextTick = fn => fn();
        state.fitCalendar = () => {};
        state.openFilters();
        state.draftLayers.tasks = false;
        assert.equal(state.layers.tasks, true);
        assert.equal(writes.length, 0);
        state.filtersOpen = false;
        state.openFilters();
        assert.equal(state.draftLayers.tasks, true);
        state.draftLayers = { tasks: false, plans: false, birthdays: false, meals: false };
        state.applyLayers();
        assert.equal(state.hasLayerFilter, true);
        assert.equal(writes.length, 1);
        assert.deepEqual(JSON.parse(writes[0]), state.draftLayers);
    } finally { globalThis.localStorage = original; }
});
