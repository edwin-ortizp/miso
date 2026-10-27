import assert from 'node:assert/strict';
import test from 'node:test';

import { createPomodoroTimer } from '../../resources/js/pomodoro-timer.js';

function harness(savedState = null, options = {}) {
    let currentTime = Date.parse('2026-07-13T15:00:00Z');
    let intervalCallback = null;
    const values = new Map(savedState ? [['pomodoro:test', JSON.stringify(savedState)]] : []);
    const savedSessions = [];
    const timer = createPomodoroTimer({
        ...options,
        workDuration: 25,
        shortBreak: 5,
        longBreak: 15,
        storageKey: 'pomodoro:test',
        saveSession: async (...args) => savedSessions.push(args),
    }, {
        now: () => currentTime,
        storage: {
            getItem: (key) => values.get(key) ?? null,
            setItem: (key, value) => values.set(key, value),
            removeItem: (key) => values.delete(key),
        },
        setInterval: (callback) => { intervalCallback = callback; return 1; },
        clearInterval: () => { intervalCallback = null; },
        uuid: () => 'e59067e6-7051-4b66-9e64-2f39e467a7b8',
        requestNotificationPermission: async () => {},
        notify: () => {},
        document: null,
    });

    return {
        timer,
        savedSessions,
        advance(milliseconds) {
            currentTime += milliseconds;
            intervalCallback?.();
        },
    };
}

test('counts from an absolute deadline and excludes paused time', () => {
    const { timer, advance } = harness();

    timer.start();
    advance(30_000);
    assert.equal(timer.timeLeft, 1470);

    timer.pause();
    advance(60_000);
    assert.equal(timer.timeLeft, 1470);

    timer.start();
    advance(10_000);
    assert.equal(timer.timeLeft, 1460);
});

test('restores a running timer and saves an expired cycle once', async () => {
    const end = Date.parse('2026-07-13T14:59:00Z');
    const start = end - (25 * 60 * 1000);
    const { timer, savedSessions } = harness({
        version: 1,
        mode: 'work',
        isRunning: true,
        pendingCompletion: false,
        timeLeft: 60,
        totalTime: 1500,
        startedAt: start,
        endsAt: end,
        cycleId: 'e59067e6-7051-4b66-9e64-2f39e467a7b8',
        description: 'Trabajo recuperado',
    });

    timer.restore();
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(savedSessions.length, 1);
    assert.deepEqual(savedSessions[0], [
        Math.floor(start / 1000),
        Math.floor(end / 1000),
        'Trabajo recuperado',
        'e59067e6-7051-4b66-9e64-2f39e467a7b8',
    ]);
    assert.equal(timer.timeLeft, 1500);
    assert.equal(timer.pendingCompletion, false);
});

test('finishes partial focus excluding paused time and rejects sub-minute entries', async () => {
 const {timer,advance,savedSessions}=harness();
 timer.start(); advance(30000); await timer.finishEarly(); assert.equal(savedSessions.length,0);
 advance(60000); timer.pause(); advance(120000); await timer.finishEarly();
 assert.equal(savedSessions.length,1);assert.equal(savedSessions[0][1]-savedSessions[0][0],90);
 await timer.finishEarly();assert.equal(savedSessions.length,1);
});


test('focus keeps task and independent notes when reopened running or paused, and restores old states', async () => {
    const { timer, advance } = harness();
    await timer.openFocus({start: true, taskId: 'task-a', description: 'Mi registro'});
    advance(1000);
    await timer.openFocus({start: true, taskId: 'task-b'});
    assert.equal(timer.taskId, 'task-a');
    timer.pause();
    await timer.openFocus({start: true, taskId: 'task-b'});
    assert.equal(timer.taskId, 'task-a');
    assert.equal(timer.description, 'Mi registro');
    timer.taskId = null;
    timer.restore();
    assert.equal(timer.taskId, 'task-a');
    timer.start();
    assert.equal(timer.taskId, 'task-a');
    const legacy = harness({version: 1, mode: 'work', timeLeft: 1400, totalTime: 1500, description: 'Anterior'}).timer;
    legacy.restore();
    assert.equal(legacy.taskId, null);
    assert.equal(legacy.description, 'Anterior');
});

test('loads title separately, does not overwrite notes typed during loading, and handles unavailable tasks', async () => {
    let resolve;
    const {timer} = harness(null, {loadTask: () => new Promise(done => {resolve = done;})});
    const opening = timer.openFocus({start: true, taskId: 'meeting'});
    assert.equal(timer.taskLoading, true);
    timer.description = 'Notas propias';
    resolve({id: 'meeting', title: 'Reunión', html: '<p>Agenda</p>', hasDescription: true});
    await opening;
    assert.equal(timer.description, 'Notas propias');
    assert.equal(timer.taskContext.title, 'Reunión');
    const missing = harness(null, {loadTask: async () => null}).timer;
    await missing.openFocus({start: true, taskId: 'deleted'});
    assert.equal(missing.taskContext, null);
    assert.equal(missing.taskLoading, false);
    const loaded = harness(null, {loadTask: async () => ({title: 'Título original'})}).timer;
    await loaded.openFocus({start: true, taskId: 'meeting'});
    assert.equal(loaded.description, 'Título original');
});
