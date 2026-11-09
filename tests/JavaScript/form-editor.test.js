import test from 'node:test';
import assert from 'node:assert/strict';
import { registerFormEditor } from '../../resources/js/form-editor.js';

function editor(call = async () => {}) {
    let factory;
    registerFormEditor({ data: (_, value) => { factory = value; } });
    const wire = { showForm: false, $set(key, value) { this[key] = value; }, $call: call };
    return { wire, controller: factory(wire, { title: '', statusId: '' }, { openForm: 'showForm' }) };
}

test('existing task opens in consultation and switching mode preserves its draft', async () => {
    const { wire, controller } = editor(async () => { wire.title = 'Tarea'; });
    await controller.openEditor({ action: 'openForm', id: 'one' });
    assert.equal(controller.editingContent, false);
    wire.statusId = 'ready';
    controller.editingContent = true;
    assert.equal(wire.statusId, 'ready');
    assert.equal(wire.title, 'Tarea');
    assert.equal(wire.showForm, true);
});

test('a late load does not reopen an editor the user closed', async () => {
    let finish;
    const { wire, controller } = editor(() => new Promise(resolve => { finish = resolve; }));
    const opening = controller.openEditor({ action: 'openForm', id: 'one' });
    controller.closeEditor();
    finish();
    await opening;
    assert.equal(wire.showForm, false);
});

test('opening a new task resets the previous draft and enables editing', async () => {
    const { wire, controller } = editor();
    wire.title = 'Anterior';
    await controller.openEditor({ action: 'openForm' });
    assert.equal(wire.title, '');
    assert.equal(controller.editingContent, true);
});
