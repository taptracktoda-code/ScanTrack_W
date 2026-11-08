import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/system-health.js', import.meta.url), 'utf8');
const flush = () => new Promise((resolve) => setImmediate(resolve));
const payload = () => ({
    overall_status: 'Healthy', server: 'Responding', api: 'Responding', database: 'Connected',
    storage: 'Writable', drivers: 2, vehicles: 3, qr_codes: 2, today_attendance: 4, total_logs: 20,
    last_scan: null, reasons: [], checked_at: '2026-11-08T12:00:00+08:00', response_time_ms: 10,
});
const response = (data = payload(), status = 200) => ({
    ok: status === 200, status, redirected: false, json: async () => data,
});

function monitor(firstResponse = response(), online = true) {
    const nodes = new Map();
    const windowEvents = {};
    const documentEvents = {};
    const timers = new Map();
    const intervals = new Map();
    const queue = [firstResponse];
    const calls = [];
    let nextId = 0;
    let now = Date.parse('2026-11-08T04:00:00Z');
    const node = (id) => {
        if (!nodes.has(id)) {
            const classes = new Set();
            nodes.set(id, {
                textContent: '', className: '', disabled: false, children: [], events: {},
                classList: { add: (...names) => names.forEach((n) => classes.add(n)), remove: (...names) => names.forEach((n) => classes.delete(n)), contains: (n) => classes.has(n) },
                addEventListener(event, callback) { this.events[event] = callback; },
                replaceChildren() { this.children = []; },
                appendChild(child) { this.children.push(child); },
            });
        }
        return nodes.get(id);
    };
    const document = {
        hidden: false, currentScript: { dataset: { systemHealthUrl: '/api/system-health' } },
        getElementById: node, createElement: () => node(`created-${++nextId}`),
        addEventListener: (name, callback) => { documentEvents[name] = callback; },
    };
    const navigator = { onLine: online };
    vm.runInNewContext(source, {
        document, navigator, AbortController,
        Date: class extends Date { static now() { return now; } },
        window: { addEventListener: (name, callback) => { windowEvents[name] = callback; } },
        setTimeout: (fn, delay) => { const id = ++nextId; timers.set(id, { fn, delay }); return id; },
        clearTimeout: (id) => timers.delete(id),
        setInterval: (fn) => { const id = ++nextId; intervals.set(id, fn); return id; },
        clearInterval: (id) => intervals.delete(id),
        fetch: (url, options) => {
            calls.push({ url, options });
            const result = queue.length ? queue.shift() : response();
            if (result === 'pending') {
                return new Promise((resolve, reject) => {
                    options.signal.addEventListener('abort', () => {
                        const error = new Error('Aborted'); error.name = 'AbortError'; reject(error);
                    });
                });
            }
            return result instanceof Error ? Promise.reject(result) : Promise.resolve(result);
        },
    });
    return {
        node, document, navigator, windowEvents, documentEvents, timers, calls, queue,
        advance(ms) { now += ms; intervals.forEach((fn) => fn()); },
        fire(delay) {
            const entry = [...timers].find(([, value]) => value.delay === delay);
            assert.ok(entry, `Expected ${delay} ms timer`);
            timers.delete(entry[0]); entry[1].fn();
        },
    };
}

test('loads immediately and polls without caching or overlapping requests', async () => {
    const app = monitor();
    await flush();
    assert.match(app.node('overall-status-label').textContent, /Healthy/);
    assert.equal(app.node('drivers-count').textContent, 2);
    assert.equal(app.calls[0].options.cache, 'no-store');
    app.queue.push('pending');
    app.fire(5000);
    app.node('refresh-system-health').events.click();
    app.node('system-health-action-btn').events.click();
    assert.equal(app.calls.length, 2);
    app.fire(10000);
    await flush();
    assert.match(app.node('system-health-error').textContent, /timed out/);
    assert.equal(app.node('drivers-count').textContent, 'Unavailable');
    app.fire(5000);
    await flush();
    assert.match(app.node('overall-status-label').textContent, /Healthy/);
});

test('network errors clear the previous healthy state and automatically recover', async () => {
    const app = monitor(); await flush();
    app.queue.push(new Error('Network failed'));
    app.fire(5000); await flush();
    assert.match(app.node('overall-status-label').textContent, /Unavailable/);
    assert.match(app.node('system-health-freshness').textContent, /Last successful check/);
    app.fire(5000); await flush();
    assert.match(app.node('overall-status-label').textContent, /Healthy/);
    assert.equal(app.node('system-health-error').classList.contains('d-none'), true);
});

test('a critical database report is rendered without inventing counts or scans', async () => {
    const app = monitor(response({ ...payload(), overall_status: 'Critical', database: 'Unavailable', drivers: null, reasons: ['Database check failed'] }));
    await flush();
    assert.match(app.node('overall-status-label').textContent, /Critical/);
    assert.equal(app.node('drivers-count').textContent, 'Unavailable');
    assert.equal(app.node('last-scan-driver').textContent, 'Unavailable');
});

test('expired sessions stop polling and tell the user to sign in', async () => {
    const app = monitor(response({}, 401)); await flush();
    assert.match(app.node('system-health-error').textContent, /sign in again/);
    assert.equal(app.node('refresh-system-health').disabled, true);
    assert.equal(app.timers.size, 0);
});

test('hidden pages pause polling and resume with a fresh check', async () => {
    const app = monitor(); await flush();
    app.document.hidden = true; app.documentEvents.visibilitychange();
    assert.equal(app.timers.size, 0);
    assert.match(app.node('system-health-freshness').textContent, /paused/);
    app.document.hidden = false; app.documentEvents.visibilitychange(); await flush();
    assert.equal(app.calls.length, 2);
    app.windowEvents.pagehide();
    assert.equal(app.timers.size, 0);
    app.windowEvents.pageshow(); await flush();
    assert.equal(app.calls.length, 3);
});

test('stale responses lose their healthy status even when timers were delayed', async () => {
    const app = monitor(); await flush();
    app.advance(16000);
    assert.match(app.node('overall-status-label').textContent, /Unavailable/);
    assert.match(app.node('system-health-error').textContent, /stale/);
});

test('offline state is visible and connection restoration triggers an immediate check', async () => {
    const app = monitor(); await flush();
    app.navigator.onLine = false; app.windowEvents.offline();
    assert.match(app.node('system-health-error').textContent, /offline/);
    app.navigator.onLine = true; app.windowEvents.online(); await flush();
    assert.match(app.node('overall-status-label').textContent, /Healthy/);
});

test('opening while offline shows an error without a stuck loading indicator', async () => {
    const app = monitor(response(), false); await flush();
    assert.equal(app.calls.length, 0);
    assert.match(app.node('system-health-error').textContent, /offline/);
    assert.equal(app.node('system-health-loading').classList.contains('d-none'), true);
    app.navigator.onLine = true; app.windowEvents.online(); await flush();
    assert.match(app.node('overall-status-label').textContent, /Healthy/);
});

test('HTTP failures and malformed responses never show healthy', async () => {
    const app = monitor(response({}, 500)); await flush();
    assert.match(app.node('overall-status-label').textContent, /Unavailable/);
    app.queue.push(response({})); app.fire(5000); await flush();
    assert.match(app.node('system-health-error').textContent, /Invalid health response/);
});
