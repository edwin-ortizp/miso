import test from 'node:test';
import assert from 'node:assert/strict';
import { registerUiSurfaces } from '../../resources/js/ui-surfaces.js';
import { registerNavigationGuard } from '../../resources/js/navigation-guard.js';
import { registerDialogViewport } from '../../resources/js/dialog-viewport.js';

test('morph clones cannot acquire surface locks, guards or viewport listeners', () => {
    const classes = new Set();
    let nativeOpen = false;
    globalThis.window = new EventTarget();
    globalThis.document = Object.assign(new EventTarget(), {
        querySelector: () => nativeOpen ? {} : null,
        body: { classList: { add: value => classes.add(value), remove: value => classes.delete(value) }, contains: () => false },
    });
    globalThis.HTMLElement = class {};
    globalThis.requestAnimationFrame = callback => callback();
    globalThis.getComputedStyle = () => ({ zIndex: '1200' });
    const directives = new Map();
    let cloning = false;
    const Alpine = {
        data() {},
        directive: (name, callback) => directives.set(name, callback),
        skipDuringClone: callback => (...args) => cloning ? undefined : callback(...args),
    };
    registerUiSurfaces(Alpine);
    registerNavigationGuard(Alpine);
    registerDialogViewport(Alpine);
    cloning = true;
    // A speculative clone must not even inspect a node or install cleanup.
    for (const callback of directives.values()) callback(null, null, null);
    assert.equal(classes.size, 0);
    cloning = false;
    const mount = () => {
        const el = Object.assign(new EventTarget(), {
            isConnected: true, getClientRects: () => [{}], hasAttribute: () => true,
            contains: () => true, querySelectorAll: () => [], closest: () => null, style: {zIndex:''},
        });
        document.activeElement = el;
        let dispose;
        directives.get('md-surface')(el, { modifiers: [], expression: '' }, { cleanup: fn => { dispose = fn; } });
        return {dispose, el};
    };
    const first = mount();
    assert.equal(classes.has('md-scroll-locked'), true);
    cloning = true;
    directives.get('md-surface')(null, null, null);
    cloning = false;
    const second = mount();
    assert.ok(Number(second.el.style.zIndex) > Number(first.el.style.zIndex));
    let closeRequests = 0;
    second.el.addEventListener('md-surface-close', () => closeRequests++);
    const escape = () => document.dispatchEvent(Object.assign(new Event('keydown'), {key:'Escape'}));
    nativeOpen = true;
    escape();
    assert.equal(closeRequests, 0, 'native error dialog owns Escape');
    nativeOpen = false;
    escape();
    assert.equal(closeRequests, 1, 'top custom surface regains Escape after dismissal');
    first.dispose(); // The real second modal must keep its own lock.
    assert.equal(classes.has('md-scroll-locked'), true);
    second.dispose();
    assert.equal(first.el.style.zIndex, '');
    assert.equal(second.el.style.zIndex, '');
    assert.equal(classes.has('md-scroll-locked'), false);
});
