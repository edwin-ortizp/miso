import test from 'node:test';
import assert from 'node:assert/strict';
import { registerFormEditor } from '../../resources/js/form-editor.js';

globalThis.document = {};

function editor(call = async () => {}, defaults = { title: '', statusId: '', descriptionMode: 'write' }) {
    let factory;
    registerFormEditor({ data: (_, value) => { factory = value; } });
    const wire = { showForm: false, $set(key, value) { this[key] = value; }, $call: call };
    return { wire, controller: factory(wire, defaults, { openForm: 'showForm' }) };
}

test('existing task opens in consultation and switching mode preserves its draft', async () => {
    const { wire, controller } = editor(async () => { wire.title = 'Tarea'; wire.descriptionMode = 'preview'; });
    await controller.openEditor({ action: 'openForm', id: 'one' });
    assert.equal(controller.editingContent, false);
    wire.statusId = 'ready';
    controller.beginContentEdit();
    assert.equal(wire.descriptionMode, 'write');
    wire.descriptionMode = 'preview';
    assert.equal(controller.editingContent, true);
    assert.equal(wire.descriptionMode, 'preview');
    assert.equal(wire.statusId, 'ready');
    assert.equal(wire.title, 'Tarea');
    assert.equal(wire.showForm, true);
});

test('a late load does not reopen an editor the user closed', async () => {
    let finish;
    const { wire, controller } = editor(() => new Promise(resolve => { finish = resolve; }));
    const opening = controller.openEditor({ action: 'openForm', id: 'one' });
    await new Promise(resolve => setImmediate(resolve));
    controller.closeEditor();
    finish();
    await opening;
    assert.equal(wire.showForm, false);
});

for (const intent of [{mode:'edit'}, {intent:'schedule'}]) test(`explicit edit survives server preview ${JSON.stringify(intent)}`, async () => {
    const {wire,controller} = editor(async () => { wire.descriptionMode='preview'; wire.description='Descripción 🌱 **completa**'; });
    await controller.openEditor({action:'openForm',id:'one',...intent});
    assert.equal(wire.descriptionMode,'write');
    assert.equal(wire.description,'Descripción 🌱 **completa**');
});

test('late explicit edit never changes mode after closing', async () => {
    let finish;
    const {wire,controller} = editor(() => new Promise(resolve => {finish=resolve;}));
    const opening=controller.openEditor({action:'openForm',id:'one',mode:'edit'});
    await new Promise(resolve => setImmediate(resolve));
    controller.closeEditor(); wire.descriptionMode='preview'; finish(); await opening;
    assert.equal(wire.descriptionMode,'preview'); assert.equal(wire.showForm,false);
});

test('edit action does not add Markdown state to other editors', async () => {
    const {wire,controller}=editor(async () => {},{title:''});
    await controller.openEditor({action:'openForm',id:'health',mode:'edit'});
    controller.beginContentEdit();
    assert.equal(Object.hasOwn(wire,'descriptionMode'),false);
});

test('opening a new task resets the previous draft and enables editing', async () => {
    const { wire, controller } = editor();
    wire.title = 'Anterior';
    await controller.openEditor({ action: 'openForm' });
    assert.equal(wire.title, '');
    assert.equal(controller.editingContent, true);
});
