import test from 'node:test';
import assert from 'node:assert/strict';
import { codePoints, createCompanionChat, fillTemplate, shouldHint, singleLine, splitLines, validateDraft, whenDate } from '../../resources/js/companion-chat.js';

const memoryStore = () => {
    const data = new Map();
    return { getItem: (k) => (data.has(k) ? data.get(k) : null), setItem: (k, v) => data.set(k, String(v)), removeItem: (k) => data.delete(k) };
};

test('separa líneas y descarta vacías', () => {
    assert.deepEqual(splitLines(' Uno \n\n Dos\r\nTres '), ['Uno', 'Dos', 'Tres']);
    assert.equal(singleLine('Uno\nDos'), 'Uno Dos');
});

test('valida borradores en ambos modos', () => {
    assert.match(validateDraft('   ', 'single'), /Escribe/);
    assert.match(validateDraft('', 'bulk'), /una por línea/);
    assert.equal(validateDraft('Comprar pan', 'single'), '');
    assert.match(validateDraft('😀'.repeat(256), 'single'), /255/);
    assert.equal(validateDraft('😀'.repeat(255), 'single'), '');
    assert.equal(codePoints('😀a'), 2);
    assert.match(validateDraft(Array.from({ length: 51 }, (_, i) => `T${i}`).join('\n'), 'bulk'), /50/);
});

test('rellena plantillas', () => {
    assert.equal(fillTemplate('¡Listas! {n} tareas', { n: 3 }), '¡Listas! 3 tareas');
});

test('guarda y recupera borradores por acción', () => {
    const storage = memoryStore();
    const config = { module: 'tasks', quick: [{ type: 'compose', id: 'one', mode: 'single' }, { type: 'compose', id: 'many', mode: 'bulk' }] };
    const chat = createCompanionChat(config, { storage });
    chat.startCompose(0);
    chat.draft = 'Pagar recibo';
    chat.persistDraft(chat.draft);
    chat.startCompose(1);
    assert.equal(chat.draft, '');
    chat.startCompose(0);
    assert.equal(chat.draft, 'Pagar recibo');
});

test('envía una vez, aunque se haga doble clic', async () => {
    const calls = [];
    const config = { followUp: '¿Algo más?', quick: [{ type: 'compose', id: 'one', mode: 'single', call: 'quickCreateTasks', reply: 'ok' }] };
    const chat = createCompanionChat(config, { storage: memoryStore() });
    chat.open = true;
    chat.wire = { call: async (...args) => { calls.push(args); return { ok: true, count: 1 }; } };
    chat.startCompose(0);
    chat.draft = 'Una';
    await Promise.all([chat.send(), chat.send()]);
    assert.equal(calls.length, 1);
    assert.deepEqual(calls[0], ['quickCreateTasks', 'Una']);
    assert.equal(chat.draft, '');
    assert.equal(chat.open, true);
    assert.equal(chat.messages.at(-1).text, '¿Algo más?');
});

test('si falla, conserva el texto', async () => {
    const config = { quick: [{ type: 'compose', id: 'one', mode: 'single', call: 'x' }] };
    const chat = createCompanionChat(config, { storage: memoryStore() });
    chat.wire = { call: async () => ({ ok: false, message: 'No' }) };
    chat.startCompose(0);
    chat.draft = 'Una';
    await chat.send();
    assert.equal(chat.draft, 'Una');
    assert.equal(chat.messages.at(-1).tone, 'error');
});

test('la conversación avanza por pasos al registrar', async () => {
    const config = { quick: [
        { type: 'tap', id: 'feliz', group: 'start', call: 'saveMood', args: ['x'], reply: 'Gracias.', then: 'energy', thenSay: '¿Y tu energía?' },
        { type: 'tap', id: 'e3', group: 'energy', call: 'quickEnergy', args: [3], reply: 'Anotado.', then: 'done' },
    ] };
    const chat = createCompanionChat(config, { storage: memoryStore() });
    chat.wire = { call: async () => null };
    await chat.tap(0);
    assert.equal(chat.step, 'energy');
    assert.equal(chat.messages.at(-1).text, '¿Y tu energía?');
    await chat.tap(1);
    assert.equal(chat.step, 'done');
});

test('usa los datos de la respuesta en el mensaje', async () => {
    const config = { quick: [{ type: 'tap', id: 'agua', call: 'quickAdd', reply: '¡Vas por {total} ml hoy!' }] };
    const chat = createCompanionChat(config, { storage: memoryStore() });
    chat.wire = { call: async () => ({ ok: true, total: 750 }) };
    await chat.tap(0);
    assert.equal(chat.messages.at(-1).text, '¡Vas por 750 ml hoy!');
});

test('la pista sale solo las primeras visitas y nunca tras el primer uso', () => {
    assert.equal(shouldHint({}), true);
    assert.equal(shouldHint({ views: 3 }), false);
    assert.equal(shouldHint({ views: 0, used: true }), false);
    const local = memoryStore();
    const chat = createCompanionChat({}, { storage: memoryStore(), local });
    chat.markUsed();
    assert.equal(JSON.parse(local.getItem('miso:companion-chat:hint')).used, true);
});

test('calcula hoy y mañana en fecha local', () => {
    const now = new Date(2026, 10, 30, 22, 0);
    assert.equal(whenDate('today', now), '2026-11-30');
    assert.equal(whenDate('tomorrow', now), '2026-12-01');
    assert.equal(whenDate('', now), null);
});
