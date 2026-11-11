import assert from 'node:assert/strict';
import test from 'node:test';

import { createFocusCompanion, focusStage } from '../../resources/js/focus-companion.js';

const base = { mode: 'work', isRunning: false, isCompleting: false, pendingCompletion: false, startedAt: null, timeLeft: 1500, totalTime: 1500 };
const memory = () => { const v = new Map(); return { getItem: (k) => v.get(k) ?? null, setItem: (k, x) => v.set(k, x), removeItem: (k) => v.delete(k) }; };
const lines = Object.fromEntries(['ready', 'start', 'half', 'final', 'paused', 'done', 'break'].map((s) => [s, [`${s}-a`, `${s}-b`]]));

test('stages follow the work cycle', () => {
    const running = { ...base, isRunning: true, startedAt: 1 };
    assert.equal(focusStage(base), 'ready');
    assert.equal(focusStage({ ...running, timeLeft: 1400 }), 'start');
    assert.equal(focusStage({ ...running, timeLeft: 750 }), 'half');
    assert.equal(focusStage({ ...running, timeLeft: 300 }), 'final');
    assert.equal(focusStage({ ...running, isRunning: false, timeLeft: 900 }), 'paused');
    assert.equal(focusStage({ ...base, isCompleting: true }), 'done');
    assert.equal(focusStage({ ...base, mode: 'shortBreak', totalTime: 300, timeLeft: 300 }), 'break');
    // Ciclos cortos: el tramo final llega por porcentaje, no por los 5 min.
    assert.equal(focusStage({ ...running, totalTime: 240, timeLeft: 200 }), 'start');
});

test('line changes only when the stage changes and done holds until next start', () => {
    const c = createFocusCompanion({ lines, cry: '¡Ral-ral!', storage: memory(), random: () => 0 });
    c.sync(base);
    assert.equal(c.line, 'ready-a');
    assert.equal(c.cry, '¡Ral-ral!');
    c.sync({ ...base, isRunning: true, startedAt: 1, timeLeft: 1490 });
    const startLine = c.line;
    c.sync({ ...base, isRunning: true, startedAt: 1, timeLeft: 1480 });
    assert.equal(c.line, startLine);
    c.sync({ ...base, isCompleting: true, startedAt: 1, timeLeft: 0 });
    assert.equal(c.stage, 'done');
    assert.equal(c.cheer, true);
    assert.equal(c.cry, '¡¡Ral-ral!!');
    c.sync(base); // tras guardar vuelve a "listo", pero sigue celebrando
    assert.equal(c.stage, 'done');
    c.sync({ ...base, mode: 'shortBreak', totalTime: 300, timeLeft: 300 });
    assert.equal(c.stage, 'break');
});

test('hide preference is remembered', () => {
    const storage = memory();
    const c = createFocusCompanion({ lines, storage, storageKey: 'k' });
    c.toggleHidden();
    assert.equal(createFocusCompanion({ lines, storage, storageKey: 'k' }).hidden, true);
    c.toggleHidden();
    assert.equal(createFocusCompanion({ lines, storage, storageKey: 'k' }).hidden, false);
});
