import test from 'node:test';
import assert from 'node:assert/strict';
import { createGameReveal } from '../../resources/js/game-reveal.js';

function setup(reduce = false) {
    const pending = new Map();
    let id = 0;
    const reveal = createGameReveal({
        reducedMotion: () => reduce,
        schedule: (callback, ms) => { pending.set(++id, { callback, ms }); return id; },
        cancel: key => pending.delete(key),
    });
    return { reveal, pending };
}
test('the ball shakes, opens and reveals, then clears timers', () => {
    const {reveal, pending} = setup();
    reveal.begin();
    assert.equal(reveal.phase, 'shake');
    assert.equal(reveal.ready, false);
    [...pending.values()].find(t => t.ms === 850).callback();
    assert.equal(reveal.phase, 'open');
    [...pending.values()].find(t => t.ms === 1850).callback();
    assert.equal(reveal.ready, true);
    assert.equal(pending.size, 0);
});
test('skip, restart and destroy cannot leave old animations running', () => {
    const {reveal, pending} = setup();
    reveal.begin();
    const oldIds = [...pending.keys()];
    reveal.begin();
    assert.ok(oldIds.every(id => !pending.has(id)));
    reveal.finish();
    assert.equal(reveal.phase, 'revealed');
    assert.equal(pending.size, 0);
    reveal.begin();
    reveal.destroy();
    assert.equal(pending.size, 0);
});
test('reduced motion shows the discovery immediately', () => {
    const {reveal, pending} = setup(true);
    reveal.begin();
    assert.equal(reveal.ready, true);
    assert.equal(pending.size, 0);
});
