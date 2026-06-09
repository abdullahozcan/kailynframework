(function () {
    'use strict';

    class KailynComponent {
        constructor(element) {
            this.el = element;
            this.id = element.getAttribute('k-id');
            this.name = element.getAttribute('k-component');
            this.state = JSON.parse(element.getAttribute('k-state') || '{}');
            this.updating = false;

            this.bindEvents();
        }

        bindEvents() {
            this.el.addEventListener('click', (e) => {
                const btn = e.target.closest('[k-on\\:click]');
                if (btn) {
                    e.preventDefault();
                    const method = btn.getAttribute('k-on:click');
                    this.callMethod(method);
                }
            });

            this.el.addEventListener('input', (e) => {
                const input = e.target.closest('[k-model]');
                if (input) {
                    const prop = input.getAttribute('k-model');
                    const value = input.type === 'checkbox' ? input.checked : input.value;
                    this.state[prop] = value;
                }
            });

            this.el.addEventListener('change', (e) => {
                const input = e.target.closest('[k-model]');
                if (input && input.type === 'select-one') {
                    const prop = input.getAttribute('k-model');
                    this.state[prop] = input.value;
                    this.callMethod('$sync', [prop]);
                }
            });

            this.el.addEventListener('keydown', (e) => {
                const input = e.target.closest('[k-on\\:keydown]');
                if (input) {
                    const method = input.getAttribute('k-on:keydown');
                    this.callMethod(method);
                }

                if (e.key === 'Enter') {
                    const input = e.target.closest('[k-on\\:keydown\\.enter]');
                    if (input) {
                        e.preventDefault();
                        const method = input.getAttribute('k-on:keydown.enter');
                        this.callMethod(method);
                    }
                }
            });

            this.el.addEventListener('submit', (e) => {
                const form = e.target.closest('[k-on\\:submit]');
                if (form) {
                    e.preventDefault();
                    const method = form.getAttribute('k-on:submit');
                    this.callMethod(method);
                }
            });

            this.el.addEventListener('dblclick', (e) => {
                const el = e.target.closest('[k-on\\:dblclick]');
                if (el) {
                    e.preventDefault();
                    const method = el.getAttribute('k-on:dblclick');
                    this.callMethod(method);
                }
            });
        }

        parseMethod(method) {
            const match = method.match(/^(\w+)\(([^)]*)\)$/);
            if (match) {
                const name = match[1];
                const args = match[2]
                    ? match[2].split(',').map((a) => a.trim()).map((a) => {
                        if (a === 'true') return true;
                        if (a === 'false') return false;
                        if (a === 'null') return null;
                        if (!isNaN(a)) return Number(a);
                        if ((a.startsWith("'") && a.endsWith("'")) || (a.startsWith('"') && a.endsWith('"'))) {
                            return a.slice(1, -1);
                        }
                        return a;
                    })
                    : [];
                return { name, params: args };
            }
            return { name: method, params: [] };
        }

        callMethod(method, params) {
            if (this.updating) return;
            this.updating = true;

            const parsed = this.parseMethod(method);
            const methodName = parsed.name;
            const methodParams = params || parsed.params;

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
                },
                body: JSON.stringify(payload),
            })
                .then((res) => res.json())
                .then((data) => {
                    this.applyUpdate(data);
                    this.updating = false;
                })
                .catch((err) => {
                    console.error('Kailyn update error:', err);
                    this.updating = false;
                });
        }

        $wire(name) {
            return (method, ...params) => this.callMethod(name + '()', ...params);
        }

        applyUpdate(data) {
            if (data.error) {
                console.error('Kailyn server error:', data.error);
                return;
            }

            if (data.state) {
                this.state = data.state;
                this.el.setAttribute('k-state', JSON.stringify(data.state));
            }

            if (data.html) {
                this.el.innerHTML = data.html;
                this.bindEvents();
            }
        }
    }

    function init() {
        const components = document.querySelectorAll('[k-component]');
        components.forEach((el) => {
            if (!el._kailyn) {
                el._kailyn = new KailynComponent(el);
            }
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
