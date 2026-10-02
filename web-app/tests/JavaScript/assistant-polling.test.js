import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/assistant-polling.js', import.meta.url), 'utf8');

function createPollingHarness(statusSequence) {
    let now = 0;
    let timerId = 0;
    let reloads = 0;
    const timers = new Map();
    const listeners = new Map();
    const hints = [{ hidden: true }];
    const state = { textContent: '' };
    const run = {
        dataset: { statusUrl: '/assistente/1/status' },
        querySelector(selector) {
            return selector === '[data-assistant-poll-hint]' ? hints[0] : state;
        },
    };
    const document = {
        hidden: false,
        querySelectorAll: () => [run],
        addEventListener(name, callback) { listeners.set(name, callback); },
    };
    const window = {
        setTimeout(callback, delay) {
            const id = ++timerId;
            timers.set(id, { callback, due: now + delay, delay });
            return id;
        },
        clearTimeout(id) { timers.delete(id); },
        location: { reload() { reloads += 1; } },
    };
    const clock = class extends Date {
        static now() { return now; }
    };
    const context = {
        Date: clock,
        Promise,
        document,
        window,
        fetch: async () => ({
            ok: true,
            json: async () => ({ status: statusSequence.shift() ?? 'queued' }),
        }),
    };

    vm.runInNewContext(source, context);

    return {
        timers,
        hints,
        state,
        listeners,
        get reloads() { return reloads; },
        set now(value) { now = value; },
        async runNextTimer() {
            const [id, timer] = [...timers.entries()].sort((a, b) => a[1].due - b[1].due)[0];
            timers.delete(id);
            now = Math.max(now, timer.due);
            await timer.callback();
            return timer;
        },
        setHidden(value) { document.hidden = value; },
    };
}

test('uses progressive intervals and slows polling while the tab is hidden', async () => {
    const harness = createPollingHarness(['queued', 'queued']);

    assert.equal((await harness.runNextTimer()).delay, 2500);
    harness.now = 15000;
    await harness.runNextTimer();
    assert.equal([...harness.timers.values()][0].delay, 5000);

    harness.setHidden(true);
    await harness.runNextTimer();
    assert.equal([...harness.timers.values()][0].delay, 30000);
});

test('stops after ten minutes and shows a manual refresh hint', async () => {
    const harness = createPollingHarness(['queued']);
    harness.now = 600000;

    await harness.runNextTimer();

    assert.equal(harness.hints[0].hidden, false);
    assert.equal(harness.timers.size, 0);
});

test('reloads when a queued run completes', async () => {
    const harness = createPollingHarness(['completed']);

    await harness.runNextTimer();

    assert.equal(harness.reloads, 1);
    assert.equal(harness.timers.size, 0);
});
