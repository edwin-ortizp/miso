import test from 'node:test';
import assert from 'node:assert/strict';
import { registerDialogViewport } from '../../resources/js/dialog-viewport.js';

test('modal viewport tracks keyboard resize/offset and cleans up on close and desktop', () => {
    const target = () => Object.assign(new EventTarget(), { height: 844, offsetTop: 0, matches: true });
    const mobile = target(), viewport = target(), win = target();
    win.visualViewport = viewport; win.matchMedia = () => mobile; win.innerHeight = 844;
    const previous = globalThis.window; globalThis.window = win;
    try {
        let directive, toggle, dispose;
        registerDialogViewport({ skipDuringClone: callback => callback, directive(name, callback) { directive = callback; } });
        const properties = new Map();
        directive({ style: { setProperty: (key, value) => properties.set(key, value), removeProperty: key => properties.delete(key) } }, { expression: 'open' }, {
            evaluateLater: () => callback => { toggle = callback; callback(true); }, effect: callback => callback(), cleanup: callback => { dispose = callback; },
        });
        assert.equal(properties.get('--dialog-viewport-height'), '844px');
        viewport.height = 420; viewport.offsetTop = 70; viewport.dispatchEvent(new Event('resize'));
        assert.equal(properties.get('--dialog-viewport-height'), '420px');
        assert.equal(properties.get('--dialog-viewport-top'), '70px');
        mobile.matches = false; mobile.dispatchEvent(new Event('change')); assert.equal(properties.size, 0);
        mobile.matches = true; mobile.dispatchEvent(new Event('change')); assert.equal(properties.size, 2);
        toggle(false); viewport.dispatchEvent(new Event('resize')); assert.equal(properties.size, 0);
        toggle(true); dispose(); viewport.dispatchEvent(new Event('resize')); assert.equal(properties.size, 0);
    } finally { globalThis.window = previous; }
});
