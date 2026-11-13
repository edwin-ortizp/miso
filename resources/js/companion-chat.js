// Chat del compañero (CHG-100): reemplaza al FAB con el Pokémon favorito.
// Un chatbot clásico, sin IA: saludo, acciones rápidas por pasos y paso al formulario completo.
// La animación nunca bloquea: el campo de texto se puede usar desde el primer cuadro.
// Tras crear, el chat sigue abierto con el foco en el campo, como una conversación.

export const TITLE_LIMIT = 255;
export const TYPING_MS = 420;
export const REPLY_TYPING_MS = 260;
export const HINT_DELAY_MS = 1200;
export const HINT_VISIBLE_MS = 5000;
export const HINT_MAX_VIEWS = 3;
const HINT_KEY = 'miso:companion-chat:hint';

/** Líneas no vacías, sin espacios sobrantes. */
export function splitLines(text) {
    return String(text ?? '').split(/\r\n|\r|\n/).map((line) => line.trim()).filter(Boolean);
}

/** Longitud visible en puntos de código (los emoji cuentan como uno). */
export function codePoints(text) {
    return Array.from(String(text ?? '')).length;
}

/** Valida un borrador. Devuelve el mensaje de error o '' si está listo. */
export function validateDraft(text, mode = 'single') {
    const lines = splitLines(text);
    if (!lines.length) return mode === 'bulk' ? 'Escribe al menos una tarea, una por línea.' : 'Escribe qué quieres anotar.';
    if (mode === 'single' && codePoints(lines.join(' ')) > TITLE_LIMIT) {
        return `Es un poco largo: máximo ${TITLE_LIMIT} caracteres. Usa «Más opciones» para agregar una descripción.`;
    }
    if (mode === 'bulk' && lines.length > 50) return 'Máximo 50 tareas a la vez.';
    return '';
}

/** Reemplaza {n}, {total}, {name}… en una plantilla de respuesta. */
export function fillTemplate(template, values = {}) {
    return String(template ?? '').replace(/\{(\w+)\}/g, (match, key) => (key in values && values[key] !== undefined ? String(values[key]) : match));
}

/** Texto final del modo sencillo: una sola línea. */
export function singleLine(text) {
    return splitLines(text).join(' ');
}

/** Fecha local YYYY-MM-DD de hoy o mañana. */
export function whenDate(when, now = new Date()) {
    if (!when) return null;
    const day = new Date(now);
    if (when === 'tomorrow') day.setDate(day.getDate() + 1);
    const pad = (value) => String(value).padStart(2, '0');
    return `${day.getFullYear()}-${pad(day.getMonth() + 1)}-${pad(day.getDate())}`;
}

/** ¿Se muestra la pista? Solo las primeras visitas y nunca después del primer uso. */
export function shouldHint(state) {
    return !state?.used && (state?.views ?? 0) < HINT_MAX_VIEWS;
}

/** Un solo chat por módulo en la página (se registra antes de moverlo a <body>). */
const hosts = new Map();

const reducedMotion = () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const readJson = (store, key) => {
    try { return JSON.parse(store?.getItem(key) ?? 'null') ?? {}; } catch { return {}; }
};

export function createCompanionChat(config = {}, { storage = null, local = null } = {}) {
    const store = storage ?? (typeof window !== 'undefined' ? window.sessionStorage : null);
    const persistent = local ?? (typeof window !== 'undefined' ? window.localStorage : null);
    const draftKey = (id) => `miso:companion-chat:${config.module ?? 'app'}:${id}`;
    const quick = config.quick ?? [];
    let sequence = 0;

    return {
        open: false,
        busy: false,
        greeted: false,
        messages: [],
        composer: null,
        step: 'start',
        draft: '',
        error: '',
        showChips: false,
        detailsOpen: false,
        hint: false,
        lastSavedAt: 0,
        wire: null,

        init() {
            this.wire = this.$wire;
            this.adoptHost();
            this.$watch?.('draft', (value) => this.persistDraft(value));
            if (typeof window === 'undefined') return;
            if (window.visualViewport) {
                const sync = () => {
                    const viewport = window.visualViewport;
                    const keyboard = Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);
                    this.$root?.style.setProperty('--chat-keyboard', `${keyboard}px`);
                };
                window.visualViewport.addEventListener('resize', sync);
                window.visualViewport.addEventListener('scroll', sync);
            }
            if (!this.disabled) this.scheduleHint();
        },

        /**
         * Lleva el chat a <body> una sola vez. Si Livewire vuelve a pintar el módulo, la copia nueva
         * solo actualiza la conexión de la existente y se retira: la conversación sigue intacta.
         */
        adoptHost() {
            if (typeof document === 'undefined') return;
            const host = this.$root?.closest('[data-chat-host]');
            if (!host) return;
            // Un solo chat por página: en los renders de Livewire el módulo puede no detectarse.
            const key = 'page';
            const existing = hosts.get(key);
            if (existing && existing !== host && existing.isConnected !== false) {
                existing._chatAdopt?.(this.wire);
                queueMicrotask(() => host.remove());
                this.disabled = true;
                return;
            }
            hosts.set(key, host);
            host._chatAdopt = (wire) => { this.wire = wire; };
            // Después de que los hijos (wire:model, combobox) se enlacen al componente.
            queueMicrotask(() => document.body.appendChild(host));
        },

        /** Pista sutil de las primeras visitas: «Tócame para anotar algo». */
        scheduleHint() {
            const state = readJson(persistent, HINT_KEY);
            if (!shouldHint(state)) return;
            persistent?.setItem(HINT_KEY, JSON.stringify({ ...state, views: (state.views ?? 0) + 1 }));
            setTimeout(() => {
                if (this.open) return;
                this.hint = true;
                setTimeout(() => { this.hint = false; }, HINT_VISIBLE_MS);
            }, HINT_DELAY_MS);
        },

        markUsed() {
            this.hint = false;
            const state = readJson(persistent, HINT_KEY);
            if (!state.used) persistent?.setItem(HINT_KEY, JSON.stringify({ ...state, used: true }));
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            this.open = true;
            this.markUsed();
            const auto = quick.findIndex((item) => item.type === 'compose' && (item.group ?? 'start') === this.step);
            if (this.composer === null && auto >= 0) this.startCompose(auto, { announce: false });
            this.$nextTick?.(() => this.focusEntry());
            if (!this.greeted) {
                this.greeted = true;
                this.say(config.greeting ?? '¿Qué anotamos?', { typing: TYPING_MS }).then(() => { this.showChips = true; });
            } else {
                this.showChips = true;
            }
        },

        close({ restoreFocus = true } = {}) {
            if (!this.open) return;
            this.open = false;
            this.error = '';
            if (restoreFocus) this.$nextTick?.(() => this.$refs?.launcher?.focus());
        },

        focusEntry() {
            const target = this.activeComposer ? this.$refs?.entry : this.$root?.querySelector('[data-chat-chip]:not([style*="display: none"])');
            target?.focus({ preventScroll: true });
        },

        /** Agrega un mensaje del compañero; con escritura simulada si hay movimiento. */
        async say(text, { typing = REPLY_TYPING_MS, tone = '' } = {}) {
            if (!text) return;
            const id = ++sequence;
            const animate = typing > 0 && !reducedMotion();
            this.messages.push({ id, from: 'pet', text: animate ? '' : text, typing: animate, tone });
            this.scrollThread();
            if (!animate) return;
            await wait(typing);
            const message = this.messages.find((item) => item.id === id);
            if (message) Object.assign(message, { text, typing: false });
            this.scrollThread();
        },

        echo(text) {
            this.messages.push({ id: ++sequence, from: 'me', text, typing: false, tone: '' });
            this.scrollThread();
        },

        scrollThread() {
            this.$nextTick?.(() => {
                const thread = this.$refs?.thread;
                if (thread) thread.scrollTop = thread.scrollHeight;
            });
        },

        get activeComposer() {
            return this.composer === null ? null : quick[this.composer] ?? null;
        },

        hasFields() {
            return (this.activeComposer?.fields ?? []).length > 0;
        },

        hasField(field) {
            return (this.activeComposer?.fields ?? []).includes(field);
        },

        /** Resumen de los detalles elegidos, para el botón plegable. */
        detailsSummary() {
            const item = this.activeComposer;
            const parts = [];
            const category = this.wire?.quickCategory;
            if (category && item?.options?.[category]) parts.push(item.options[category]);
            const when = this.wire?.quickWhen;
            if (when && item?.whenLabels?.[when]) parts.push(item.whenLabels[when] + (this.wire?.quickTime ? ` · ${this.wire.quickTime}` : ''));
            return parts.length ? parts.join(' · ') : 'Detalles';
        },

        startCompose(index, { announce = true } = {}) {
            const item = quick[index];
            if (!item) return;
            this.composer = index;
            this.error = '';
            this.draft = store?.getItem(draftKey(item.id)) ?? '';
            if (announce && item.prompt) this.say(item.prompt);
            this.$nextTick?.(() => this.$refs?.entry?.focus({ preventScroll: true }));
        },

        /** Cambia de paso de la conversación (chips del grupo `then`). */
        async goTo(index) {
            const item = quick[index];
            if (!item) return;
            this.composer = null;
            this.step = item.then ?? 'start';
            if (item.say) await this.say(item.say);
        },

        persistDraft(value) {
            const item = this.activeComposer;
            if (!item || !store) return;
            value ? store.setItem(draftKey(item.id), value) : store.removeItem(draftKey(item.id));
        },

        clearDraft(item) {
            store?.removeItem(draftKey(item.id));
            this.draft = '';
        },

        get draftCount() {
            return splitLines(this.draft).length;
        },

        submitLabel() {
            const item = this.activeComposer;
            if (!item) return '';
            return item.mode === 'bulk' && this.draftCount > 1 ? fillTemplate(item.submitMany ?? 'Crear {n}', { n: this.draftCount }) : (item.submit ?? 'Crear');
        },

        onKeydown(event) {
            const item = this.activeComposer;
            if (event.key === 'Enter' && (item?.mode !== 'bulk' || event.ctrlKey || event.metaKey) && !event.shiftKey) {
                event.preventDefault();
                this.send();
            }
        },

        /** Envía el borrador al método Livewire del módulo; el chat sigue abierto. */
        async send() {
            const item = this.activeComposer;
            if (!item || this.busy || Date.now() - this.lastSavedAt < 500) return;
            this.error = validateDraft(this.draft, item.mode);
            if (this.error) {
                this.$refs?.entry?.focus();
                return;
            }
            const lines = splitLines(this.draft);
            const text = item.mode === 'single' ? singleLine(this.draft) : lines.join('\n');
            this.busy = true;
            this.echo(item.mode === 'single' ? text : lines.map((line) => `• ${line}`).join('\n'));
            const draftBackup = this.draft;
            this.clearDraft(item);
            try {
                const [result] = await Promise.all([
                    this.wire.call(item.call, text),
                    reducedMotion() ? null : wait(REPLY_TYPING_MS),
                ]);
                if (result && result.ok === false) {
                    this.draft = draftBackup;
                    await this.say(result.message ?? config.error, { tone: 'error' });
                } else {
                    this.lastSavedAt = Date.now();
                    const total = result?.count ?? (item.mode === 'single' ? 1 : lines.length);
                    await this.say(fillTemplate(total > 1 ? item.replyMany : item.reply, { n: total, text, ...(result ?? {}) }), { tone: 'success' });
                    await this.say(item.followUp ?? config.followUp);
                }
            } catch {
                this.draft = draftBackup;
                await this.say(config.error ?? 'Algo falló al guardar. Tu texto sigue aquí.', { tone: 'error' });
            } finally {
                this.busy = false;
                this.$nextTick?.(() => this.$refs?.entry?.focus({ preventScroll: true }));
            }
        },

        /** Registro de un toque; con `then` la conversación pasa al siguiente paso. */
        async tap(index) {
            const item = quick[index];
            if (!item || this.busy) return;
            this.busy = true;
            this.echo(item.echo ?? item.label);
            try {
                const [result] = await Promise.all([
                    this.wire.call(item.call, ...(item.args ?? [])),
                    reducedMotion() ? null : wait(REPLY_TYPING_MS),
                ]);
                if (result && result.ok === false) {
                    await this.say(result.message ?? config.error, { tone: 'error' });
                    return;
                }
                await this.say(fillTemplate(item.reply ?? '¡Listo!', result ?? {}), { tone: 'success' });
                if (item.then) {
                    this.step = item.then;
                    this.composer = null;
                }
                await this.say(item.thenSay ?? '');
            } catch {
                await this.say('Algo falló al registrar. Inténtalo de nuevo.', { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },

        /** Pasa el borrador y los detalles al formulario completo del módulo. */
        moreOptions() {
            const item = this.activeComposer;
            if (!item?.more) return;
            const prefill = {};
            const text = item.mode === 'single' ? singleLine(this.draft) : this.draft;
            if (text && item.more.field) prefill[item.more.field] = text;
            if (this.hasField('category') && this.wire?.quickCategory) prefill.category = this.wire.quickCategory;
            const date = this.hasField('when') ? whenDate(this.wire?.quickWhen) : null;
            if (date) {
                prefill.startDate = date;
                if (this.wire?.quickTime) prefill.startTime = this.wire.quickTime;
            }
            window.dispatchEvent(new CustomEvent(item.more.event, { detail: { ...(item.more.detail ?? {}), prefill } }));
            this.clearDraft(item);
            this.close({ restoreFocus: false });
        },
    };
}

export function registerCompanionChat(Alpine) {
    Alpine.data('companionChat', (config) => createCompanionChat(config));
}
