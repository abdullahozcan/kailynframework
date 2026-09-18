(function () {
    'use strict';

    // ─── CSRF Token ────────────────────────────────────────────

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        const input = document.querySelector('input[name="_token"]');
        if (input) return input.value;
        return '';
    }

    // ─── Method Parser ─────────────────────────────────────────

    function parseMethodExpression(expr) {
        const match = expr.match(/^([^(]+)\(([^)]*)\)$/);
        if (!match) return { name: expr, params: [] };

        const name = match[1];
        const raw = match[2].trim();
        if (!raw) return { name, params: [] };

        const params = raw.split(',').map(a => a.trim()).map(a => {
            if (a === 'true') return true;
            if (a === 'false') return false;
            if (a === 'null') return null;
            if (a === 'undefined') return undefined;
            if (!isNaN(a) && a !== '') return Number(a);
            if ((a.startsWith("'") && a.endsWith("'")) || (a.startsWith('"') && a.endsWith('"')))
                return a.slice(1, -1);
            return a;
        });

        return { name, params };
    }

    // ─── Modifier Parser ───────────────────────────────────────

    function parseModifiers(attrValue) {
        const parts = attrValue.split('.');
        const method = parts[0];
        const modifiers = parts.slice(1);
        return { method, modifiers };
    }

    function hasModifier(modifiers, name) {
        return modifiers.includes(name);
    }

    function getModifierValue(modifiers, name) {
        for (const mod of modifiers) {
            if (mod.startsWith(name + ':')) return mod.slice(name.length + 1);
            if (mod.startsWith(name + '(') && mod.endsWith(')'))
                return mod.slice(name.length + 1, -1);
        }
        return null;
    }

    // ─── Debounce Utility ──────────────────────────────────────

    function debounce(fn, ms) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), ms);
        };
    }

    // ─── Loading Manager ───────────────────────────────────────

    class LoadingManager {
        constructor() {
            this._loadingElements = new Map();
        }

        start(component, prop) {
            if (!prop) return;
            const elements = component.el.querySelectorAll(`[k-loading="${prop}"]`);
            elements.forEach(el => {
                el.disabled = true;
                el.classList.add('k-loading');
                if (!el.dataset.kOriginalHtml) {
                    el.dataset.kOriginalHtml = el.innerHTML;
                }
                el.innerHTML = '<span class="k-spinner"></span>' + el.innerHTML;
            });
            this._loadingElements.set(prop, elements);
        }

        stop(component, prop) {
            if (!prop) return;
            const elements = this._loadingElements.get(prop);
            if (!elements) return;
            elements.forEach(el => {
                el.disabled = false;
                el.classList.remove('k-loading');
                if (el.dataset.kOriginalHtml) {
                    el.innerHTML = el.dataset.kOriginalHtml;
                    delete el.dataset.kOriginalHtml;
                }
            });
            this._loadingElements.delete(prop);
        }

        stopAll(component) {
            for (const [prop] of this._loadingElements) {
                this.stop(component, prop);
            }
        }
    }

    const loadingManager = new LoadingManager();

    // ─── Request Batch Queue ───────────────────────────────────

    class RequestBatch {
        constructor() {
            this._queue = [];
            this._scheduled = false;
        }

        add(component, method, params, options) {
            this._queue.push({ component, method, params, options });
            if (!this._scheduled) {
                this._scheduled = true;
                queueMicrotask(() => this.flush());
            }
        }

        flush() {
            this._scheduled = false;
            const batch = this._queue.splice(0);

            if (batch.length === 0) return;

            if (batch.length === 1) {
                const { component, method, params, options } = batch[0];
                component._executeSingle(method, params, options);
                return;
            }

            const grouped = new Map();
            for (const item of batch) {
                const key = item.component.id;
                if (!grouped.has(key)) grouped.set(key, []);
                grouped.get(key).push(item);
            }

            for (const [id, items] of grouped) {
                const component = items[0].component;
                const payloads = items.map(i => ({
                    method: i.method,
                    params: i.params
                }));
                component._executeBatch(payloads, items[0].options);
            }
        }
    }

    const batchQueue = new RequestBatch();

    // ─── Polling Manager ───────────────────────────────────────

    class PollingManager {
        constructor() {
            this._intervals = new Map();
        }

        start(component) {
            const pollEls = component.el.querySelectorAll('[k-poll]');
            pollEls.forEach(el => {
                const raw = el.getAttribute('k-poll');
                let interval = 5000;
                let method = '';

                if (raw) {
                    const match = raw.match(/^(\d+[smh])?\s*(.*)$/);
                    if (match) {
                        if (match[1]) {
                            const val = parseInt(match[1]);
                            const unit = match[1].slice(-1);
                            interval = val * (unit === 's' ? 1000 : unit === 'm' ? 60000 : 3600000);
                        }
                        method = match[2] || '';
                    }
                }

                if (!method) return;

                const key = component.id + ':' + method;
                if (this._intervals.has(key)) return;

                const id = setInterval(() => {
                    if (!document.body.contains(component.el)) {
                        this.stop(component, method);
                        return;
                    }
                    component._executeSingle(method, [], { silent: true });
                }, interval);

                this._intervals.set(key, id);
            });
        }

        stop(component, method) {
            const key = component.id + ':' + method;
            if (this._intervals.has(key)) {
                clearInterval(this._intervals.get(key));
                this._intervals.delete(key);
            }
        }

        stopAll(component) {
            for (const [key] of this._intervals) {
                if (key.startsWith(component.id + ':')) {
                    clearInterval(this._intervals.get(key));
                    this._intervals.delete(key);
                }
            }
        }
    }

    const pollingManager = new PollingManager();

    // ─── Transition Manager ────────────────────────────────────

    class TransitionManager {
        static patch(container, newHtml) {
            const hasTransition = container.querySelector('[k-transition]');

            if (!hasTransition) {
                container.innerHTML = newHtml;
                return;
            }

            const fadeEls = container.querySelectorAll('[k-transition]');
            fadeEls.forEach(el => {
                el.style.transition = 'opacity 0.2s ease';
                el.style.opacity = '0';
            });

            setTimeout(() => {
                container.innerHTML = newHtml;
                const newFadeEls = container.querySelectorAll('[k-transition]');
                newFadeEls.forEach(el => {
                    el.style.transition = 'opacity 0.2s ease';
                    el.style.opacity = '0';
                    requestAnimationFrame(() => {
                        el.style.opacity = '1';
                    });
                });
            }, 200);
        }
    }

    // ─── KailynComponent ───────────────────────────────────────

    class KailynComponent {
        constructor(element) {
            this.el = element;
            this.id = element.getAttribute('k-id');
            this.name = element.getAttribute('k-component');
            this.state = JSON.parse(element.getAttribute('k-state') || '{}');
            this.updating = false;
            this._debouncers = {};

            this.bindEvents();
            pollingManager.start(this);
        }

        // ── Event Binding ──────────────────────────────────────

        bindEvents() {
            this._delegate('click', '[k-on\\:click]');
            this._delegate('dblclick', '[k-on\\:dblclick]');
            this._delegate('submit', '[k-on\\:submit]');
            this._delegateKeydown();
            this._bindModel();
            this._bindPoll();
        }

        _delegate(event, selector) {
            this.el.addEventListener(event, (e) => {
                const target = e.target.closest(selector);
                if (!target) return;

                const attrValue = target.getAttribute('k-on:' + event);
                const { method, modifiers } = parseModifiers(attrValue);

                if (hasModifier(modifiers, 'prevent')) e.preventDefault();
                if (hasModifier(modifiers, 'stop')) e.stopPropagation();

                this.callMethod(method);
            });
        }

        _delegateKeydown() {
            this.el.addEventListener('keydown', (e) => {
                const target = e.target.closest('[k-on\\:keydown]');
                if (!target) return;

                const attrValue = target.getAttribute('k-on:keydown');
                const { method, modifiers } = parseModifiers(attrValue);

                const keyMap = {
                    'enter': 'Enter',
                    'escape': 'Escape',
                    'tab': 'Tab',
                    'space': ' ',
                    'arrowup': 'ArrowUp',
                    'arrowdown': 'ArrowDown',
                    'arrowleft': 'ArrowLeft',
                    'arrowright': 'ArrowRight',
                    'delete': 'Delete',
                    'backspace': 'Backspace',
                };

                for (const mod of modifiers) {
                    const expectedKey = keyMap[mod.toLowerCase()];
                    if (expectedKey && e.key === expectedKey) {
                        if (hasModifier(modifiers, 'prevent')) e.preventDefault();
                        if (hasModifier(modifiers, 'stop')) e.stopPropagation();
                        this.callMethod(method);
                        return;
                    }
                }

                if (modifiers.length === 0) {
                    this.callMethod(method);
                }
            });
        }

        _bindModel() {
            this.el.addEventListener('input', (e) => {
                const input = e.target.closest('[k-model]');
                if (!input) return;

                const attrValue = input.getAttribute('k-model');
                const { method: prop, modifiers } = parseModifiers(attrValue);
                const value = input.type === 'checkbox' ? input.checked : input.value;

                this.state[prop] = value;
                this._syncState();

                const debounceMs = getModifierValue(modifiers, 'debounce');
                if (debounceMs) {
                    if (!this._debouncers[prop]) {
                        this._debouncers[prop] = debounce((p) => {
                            this.callMethod('$sync', [p]);
                        }, parseInt(debounceMs));
                    }
                    this._debouncers[prop](prop);
                }
            });

            this.el.addEventListener('change', (e) => {
                const input = e.target.closest('[k-model]');
                if (!input) return;

                if (input.type === 'select-one') {
                    const attrValue = input.getAttribute('k-model');
                    const { method: prop } = parseModifiers(attrValue);
                    this.state[prop] = input.value;
                    this.callMethod('$sync', [prop]);
                }

                if (input.type === 'checkbox') {
                    const attrValue = input.getAttribute('k-model');
                    const { method: prop } = parseModifiers(attrValue);
                    this.state[prop] = input.checked;
                    this.callMethod('$sync', [prop]);
                }
            });
        }

        _bindPoll() {
            // polling is managed by PollingManager
        }

        // ── State Sync ─────────────────────────────────────────

        _syncState() {
            this.el.setAttribute('k-state', JSON.stringify(this.state));
        }

        // ── Method Calls ───────────────────────────────────────

        callMethod(method, params, options = {}) {
            if (this.updating && !options.silent) return;

            batchQueue.add(this, method, params || [], options);
        }

        _executeSingle(method, params = [], options = {}) {
            const parsed = parseMethodExpression(method);
            const methodName = parsed.name;
            const methodParams = params.length > 0 ? params : parsed.params;

            const loadingProp = this._findLoadingProp(methodName);
            if (loadingProp) loadingManager.start(this, loadingProp);

            if (options.optimistic) {
                this._applyOptimistic(methodName, methodParams);
            }

            this.updating = true;

            const payload = {
                component: this.name,
                method: methodName,
                state: this.state,
                params: methodParams,
            };

            fetch('/_kailyn/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(payload),
            })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    if (data.error) {
                        this._handleError(data.error, methodName);
                        if (options.optimistic) this._revertOptimistic(methodName);
                        return;
                    }
                    this.applyUpdate(data);
                })
                .catch(err => {
                    console.error('Kailyn update error:', err);
                    if (options.optimistic) this._revertOptimistic(methodName);
                    this._handleError(err.message, methodName);
                })
                .finally(() => {
                    this.updating = false;
                    if (loadingProp) loadingManager.stop(this, loadingProp);
                });
        }

        _executeBatch(payloads, options = {}) {
            const loadingProp = this._findLoadingProp(payloads[0].method);
            if (loadingProp) loadingManager.start(this, loadingProp);

            this.updating = true;

            const batchPayload = {
                component: this.name,
                batch: payloads,
                state: this.state,
            };

            fetch('/_kailyn/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(batchPayload),
            })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    if (data.error) {
                        this._handleError(data.error, 'batch');
                        return;
                    }
                    if (data.results) {
                        for (const result of data.results) {
                            if (result.html || result.state) {
                                this.applyUpdate(result);
                            }
                        }
                    } else {
                        this.applyUpdate(data);
                    }
                })
                .catch(err => {
                    console.error('Kailyn batch error:', err);
                    this._handleError(err.message, 'batch');
                })
                .finally(() => {
                    this.updating = false;
                    if (loadingProp) loadingManager.stop(this, loadingProp);
                });
        }

        // ── Loading Detection ──────────────────────────────────

        _findLoadingProp(method) {
            const loadingEls = this.el.querySelectorAll('[k-loading]');
            for (const el of loadingEls) {
                const attr = el.getAttribute('k-on:click') ||
                             el.getAttribute('k-on:submit');
                if (attr) {
                    const { name } = parseMethodExpression(attr);
                    if (name === method) {
                        return el.getAttribute('k-loading');
                    }
                }
            }
            return null;
        }

        // ── Optimistic Updates ─────────────────────────────────

        _applyOptimistic(method, params) {
            this._optimisticSnapshot = JSON.parse(JSON.stringify(this.state));

            const parsed = parseMethodExpression(method);
            const name = parsed.name;
            const p = params.length > 0 ? params : parsed.params;

            if (name === 'toggle' && this.state[p[0]] !== undefined) {
                this.state[p[0]] = !this.state[p[0]];
            } else if (name.startsWith('set') && p.length >= 2) {
                const prop = p[0];
                if (this.state[prop] !== undefined) {
                    this.state[prop] = p[1];
                }
            } else if (name.startsWith('toggle')) {
                const prop = name.slice(6);
                if (prop && this.state[prop] !== undefined) {
                    this.state[prop] = !this.state[prop];
                }
            }

            this._syncState();
        }

        _revertOptimistic(method) {
            if (this._optimisticSnapshot) {
                this.state = this._optimisticSnapshot;
                this._optimisticSnapshot = null;
                this._syncState();
            }
        }

        // ── Error Handling ─────────────────────────────────────

        _handleError(message, method) {
            const errorEls = this.el.querySelectorAll('[k-error]');
            errorEls.forEach(el => {
                el.textContent = message;
                el.style.display = 'block';
            });

            this.el.setAttribute('k-has-error', 'true');
            this.el.setAttribute('k-error-message', message);

            const eventName = 'kailyn:error';
            this.el.dispatchEvent(new CustomEvent(eventName, {
                detail: { message, method, component: this },
                bubbles: true
            }));
        }

        // ── Apply Server Update ────────────────────────────────

        applyUpdate(data) {
            if (data.state) {
                this.state = data.state;
                this._syncState();
            }

            if (data.html) {
                TransitionManager.patch(this.el, data.html);
                this.bindEvents();
            }
        }

        // ── Wire Helper ────────────────────────────────────────

        $wire(name) {
            return (...params) => this.callMethod(name + '()', params);
        }

        // ── Destroy ────────────────────────────────────────────

        destroy() {
            pollingManager.stopAll(this);
            loadingManager.stopAll(this);
        }
    }

    // ─── Initialization ────────────────────────────────────────

    function init() {
        const components = document.querySelectorAll('[k-component]');
        components.forEach(el => {
            if (!el._kailyn) {
                el._kailyn = new KailynComponent(el);
            }
        });

        const destroyEls = document.querySelectorAll('[k-destroy]');
        destroyEls.forEach(el => {
            const observer = new MutationObserver(() => {
                if (!document.body.contains(el) && el._kailyn) {
                    el._kailyn.destroy();
                    observer.disconnect();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('kailyn:refresh', init);

    window.Kailyn = {
        init: init,
        refresh: init,
    };
})();
