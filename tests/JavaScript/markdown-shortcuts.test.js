import test from 'node:test';
import assert from 'node:assert/strict';
import { shortcutAction, shortcutAria, shortcutLabel, markdownShortcuts, withShortcut } from '../../resources/js/markdown-shortcuts.js';

const key = (k, extra = {}) => ({ key: k, code: `Key${k.toUpperCase()}`, ctrlKey: false, metaKey: false, altKey: false, shiftKey: false, ...extra });

test('rotula los atajos según la plataforma', () => {
    assert.equal(withShortcut('Negrita', 'bold', false), 'Negrita (Ctrl+B)');
    assert.equal(withShortcut('Negrita', 'bold', true), 'Negrita (⌘+B)');
    assert.equal(withShortcut('Checklist', 'checklist', false), 'Checklist (Ctrl+L)');
    assert.equal(withShortcut('Título', 'heading', false), 'Título');
    assert.equal(shortcutLabel(markdownShortcuts.copy, false), 'Alt+Shift+C');
    assert.equal(shortcutAria(markdownShortcuts.expand, false), 'Control+Shift+F');
});

test('reconoce las combinaciones exactas', () => {
    assert.equal(shortcutAction(key('b', { ctrlKey: true }), false), 'bold');
    assert.equal(shortcutAction(key('b', { metaKey: true }), true), 'bold');
    assert.equal(shortcutAction(key('k', { ctrlKey: true }), false), 'link');
    assert.equal(shortcutAction(key('F', { ctrlKey: true, shiftKey: true }), false), 'expand');
    assert.equal(shortcutAction(key('ç', { code: 'KeyC', altKey: true, shiftKey: true }), true), 'copy');
    assert.equal(shortcutAction(key('b'), false), null);
    assert.equal(shortcutAction(key('b', { ctrlKey: true, shiftKey: true }), false), null);
    // Ctrl+L la ejecuta app.js: no se duplica aquí.
    assert.equal(shortcutAction(key('l', { ctrlKey: true }), false), null);
});
