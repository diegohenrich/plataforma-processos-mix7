import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/demands/index.blade.php', import.meta.url), 'utf8');
const scriptMatch = blade.match(/<script>\s*\(\(\) => \{\s*const columns = \[\.\.\.document\.querySelectorAll\('\[data-kanban-more\]'\)\];[\s\S]*?<\/script>/);
assert.ok(scriptMatch, 'the rendered board controls script exists in the Blade view');
const source = scriptMatch[0].replace(/^<script>|<\/script>$/g, '');

class FakeDetails {
    constructor(open = false) {
        this._open = open;
        this.listeners = new Map();
    }

    get open() { return this._open; }

    set open(value) {
        const changed = this._open !== Boolean(value);
        this._open = Boolean(value);
        if (changed) this.emit('toggle');
    }

    addEventListener(name, callback) {
        const callbacks = this.listeners.get(name) ?? [];
        callbacks.push(callback);
        this.listeners.set(name, callbacks);
    }

    emit(name) {
        for (const callback of this.listeners.get(name) ?? []) callback();
    }
}

class FakeButton {
    constructor() {
        this.disabled = false;
        this.listeners = new Map();
    }

    addEventListener(name, callback) {
        const callbacks = this.listeners.get(name) ?? [];
        callbacks.push(callback);
        this.listeners.set(name, callbacks);
    }

    click() {
        if (this.disabled) return;
        for (const callback of this.listeners.get('click') ?? []) callback();
    }
}

function renderControls(openStates) {
    const columns = openStates.map((open) => new FakeDetails(open));
    const expandButton = new FakeButton();
    const collapseButton = new FakeButton();
    const document = {
        querySelectorAll: (selector) => selector === '[data-kanban-more]' ? columns : [],
        querySelector: (selector) => selector === '[data-kanban-expand-all]' ? expandButton
            : selector === '[data-kanban-collapse-all]' ? collapseButton : null,
    };

    vm.runInNewContext(source, { document });

    return { columns, expandButton, collapseButton };
}

test('global controls expand and collapse every demand stage', () => {
    const { columns, expandButton, collapseButton } = renderControls(Array(8).fill(false));

    assert.deepEqual(columns.map((column) => column.open), Array(8).fill(false));
    assert.equal(expandButton.disabled, false);
    assert.equal(collapseButton.disabled, true);

    expandButton.click();
    assert.deepEqual(columns.map((column) => column.open), Array(8).fill(true));
    assert.equal(expandButton.disabled, true);
    assert.equal(collapseButton.disabled, false);

    collapseButton.click();
    assert.deepEqual(columns.map((column) => column.open), Array(8).fill(false));
    assert.equal(expandButton.disabled, false);
    assert.equal(collapseButton.disabled, true);
});

test('individual stage toggles keep global button availability in sync', () => {
    const { columns, expandButton, collapseButton } = renderControls([false, false, false]);

    columns[0].open = true;
    assert.equal(expandButton.disabled, false);
    assert.equal(collapseButton.disabled, false);

    columns[1].open = true;
    columns[2].open = true;
    assert.equal(expandButton.disabled, true);
    assert.equal(collapseButton.disabled, false);

    columns[1].open = false;
    assert.equal(expandButton.disabled, false);
    assert.equal(collapseButton.disabled, false);
});
