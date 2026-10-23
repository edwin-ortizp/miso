import assert from 'node:assert/strict';
import test from 'node:test';

import { createPomodoroTimer } from '../../resources/js/pomodoro-timer.js';

function harness(savedState = null) {
    let currentTime = Date.parse('2026-07-13T15:00:00Z');
    let intervalCallback = null;
    const values = new Map(savedState ? [['pomodoro:test', JSON.stringify(savedState)]] : []);
    const savedSessions = [];
    const timer = createPomodoroTimer({
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
