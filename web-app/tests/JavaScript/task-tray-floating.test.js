import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/task-tray-floating.js', import.meta.url), 'utf8');

class FakeElement {
    constructor(tagName = 'div') {
        this.tagName = tagName;
        this.children = [];
        this.dataset = {};
        this.attributes = {};
        this.listeners = new Map();
        this.classes = new Set();
        this.classList = {
            add: (name) => this.classes.add(name),
            toggle: (name, force) => force ? this.classes.add(name) : this.classes.delete(name),
        };
        this._textContent = '';
    }

    set textContent(value) {
        this._textContent = String(value);
        this.children = [];
    }

    get textContent() {
        return [this._textContent, ...this.children.map((child) => child.textContent)].join('');
    }

    get childElementCount() { return this.children.length; }
    get lastElementChild() { return this.children.at(-1); }

    append(...nodes) {
        for (const node of nodes) {
            if (node && typeof node === 'object') {
                node.parentElement = this;
                this.children.push(node);
            }
        }
    }

    replaceChildren(...nodes) {
        this.children = [];
        this.append(...nodes);
    }

    addEventListener(name, callback) {
        const callbacks = this.listeners.get(name) ?? [];
        callbacks.push(callback);
        this.listeners.set(name, callbacks);
    }

    async click() {
        const callbacks = this.listeners.get('click') ?? [];
        for (const callback of callbacks) await callback({ preventDefault() {} });
    }

    setAttribute(name, value) { this.attributes[name] = value; }

    matches(selector) {
        if (selector === '[role="status"]') return this.attributes.role === 'status';
        const dataMatch = selector.match(/^\[data-([a-z0-9-]+)\]$/);
        if (!dataMatch) return false;
        const key = dataMatch[1].replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
        return Object.hasOwn(this.dataset, key);
    }

    querySelector(selector) {
        for (const child of this.children) {
            if (child.matches(selector)) return child;
            const nested = child.querySelector(selector);
            if (nested) return nested;
        }
        return null;
    }

    findByText(text) {
        if (this._textContent === text) return this;
        for (const child of this.children) {
            const found = child.findByText(text);
            if (found) return found;
        }
        return null;
    }
}

class FakeDocument {
    constructor() {
        this.head = new FakeElement('head');
        this.body = new FakeElement('body');
    }

    createElement(tagName) { return new FakeElement(tagName); }

    querySelector(selector) {
        return this.head.querySelector(selector) ?? this.body.querySelector(selector);
    }
}

class FakePictureInPictureWindow {
    constructor() {
        this.closed = false;
        this.document = new FakeDocument();
        this.listeners = new Map();
        this.intervals = new Map();
        this.nextInterval = 0;
    }

    setInterval(callback, delay) {
        const id = ++this.nextInterval;
        this.intervals.set(id, { callback, delay });
        return id;
    }

    clearInterval(id) { this.intervals.delete(id); }

    addEventListener(name, callback) {
        const callbacks = this.listeners.get(name) ?? [];
        callbacks.push(callback);
        this.listeners.set(name, callbacks);
    }
}

function createHarness({ supported = true } = {}) {
    const tray = new FakeElement('aside');
    const button = new FakeElement('button');
    const status = new FakeElement('p');
    const stateElement = new FakeElement('script');
    const state = {
        tasks: [{
            id: 7,
            title: 'Preparar o criativo',
            demandTitle: 'Campanha fictícia',
            demandUrl: '/demandas/12',
            statusLabel: 'A fazer',
            canStart: true,
            blocked: false,
            canComplete: true,
            waitingSince: new Date(Date.now() - (2 * 3600 + 52 * 60) * 1000).toISOString(),
            startUrl: '/tarefas/7/cronometro/iniciar',
            pauseUrl: '/tarefas/7/cronometro/pausar',
            completeUrl: '/tarefas/7/status',
        }],
        active: null,
        csrfToken: 'fake-csrf',
        heartbeatUrl: '/tarefas/cronometro/sinal',
        boardUrl: '/tarefas/quadro',
    };
    stateElement.textContent = JSON.stringify(state);
    tray.querySelector = (selector) => selector === '[data-task-tray-floating-open]'
        ? button
        : selector === '[data-task-tray-floating-status]' ? status : null;
    const mainDocument = {
        querySelector(selector) {
            if (selector === '[data-task-tray-floating]') return tray;
            if (selector === '[data-task-tray-floating-state]') return stateElement;
            return null;
        },
    };
    const pip = new FakePictureInPictureWindow();
    const documentPictureInPicture = {
        window: null,
        async requestWindow() {
            this.window = pip;
            return pip;
        },
    };
    const requests = [];
    let reloads = 0;
    const mainWindow = {
        isSecureContext: supported,
        location: { reload() { reloads += 1; } },
        focus() {},
        documentPictureInPicture: supported ? documentPictureInPicture : undefined,
    };
    const fetch = async (url, options) => {
        requests.push({ url, options });
        const action = url.includes('/iniciar') ? 'start' : url.includes('/pausar') ? 'pause' : 'complete';
        const data = action === 'start'
            ? { task_id: 7, status_label: 'Em execução', timer: { started_at: '2026-09-27T12:00:00.000Z', ended_at: null } }
            : action === 'pause'
                ? { task_id: 7, status_label: 'Pausada', timer: { duration_seconds: 35, ended_at: '2026-09-27T12:00:35.000Z' } }
                : { task_id: 7, status_label: 'Concluída', timer: null };
        return { ok: true, async json() { return { message: 'ok', data }; } };
    };

    vm.runInNewContext(source, { document: mainDocument, window: mainWindow, fetch, Date, JSON, Number, String, Promise });

    return {
        button,
        status,
        pip,
        requests,
        get reloads() { return reloads; },
    };
}

test('opens a task window and controls the timer and task through authenticated JSON actions', async () => {
    const app = createHarness();
    await app.button.click();
    assert.ok(app.pip.document.body.findByText('Minhas tarefas'));
    assert.ok(app.pip.document.body.findByText('Preparar o criativo'));
    assert.ok(app.pip.document.body.findByText('Na sua fila há 2 horas e 52 minutos. Inicie o cronômetro ou avise a gestão se estiver impedido.'));

    await app.pip.document.body.findByText('Iniciar').click();
    assert.ok(app.pip.document.body.findByText('Pausar'));
    assert.ok(app.pip.document.body.findByText('Concluir tarefa'));
    assert.equal(app.requests[0].url, '/tarefas/7/cronometro/iniciar');
    assert.equal(app.requests[0].options.headers['X-CSRF-TOKEN'], 'fake-csrf');

    await app.pip.document.body.findByText('Pausar').click();
    assert.ok(app.pip.document.body.findByText('Iniciar'));
    assert.ok(app.pip.document.body.findByText('Tempo salvo e tarefa pausada.'));

    await app.pip.document.body.findByText('Concluir').click();
    assert.equal(app.requests.at(-1).options.method, 'PATCH');
    assert.equal(JSON.parse(app.requests.at(-1).options.body).status, 'completed');
    assert.ok(app.pip.document.body.findByText('Nenhuma tarefa aberta atribuída a você.'));
    assert.equal(app.reloads, 3);
});

test('clearly disables the floating window when the browser API is unavailable', () => {
    const app = createHarness({ supported: false });
    assert.equal(app.button.disabled, true);
    assert.equal(app.status.textContent, 'Seu navegador não permite abrir uma janela separada. Use Minhas tarefas no CRM.');
});
