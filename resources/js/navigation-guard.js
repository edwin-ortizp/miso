// Only booleans and capabilities cross the native boundary; form values stay in memory.
const stack = [];
const forms = new Map();
let confirming = false;
const fieldName = e => e.name || e.id || [...(e.attributes ?? [])].find(a => a.name.startsWith('wire:model'))?.value;
export const formSnapshot = (fields) => JSON.stringify([...fields].filter(fieldName).filter(e => !['submit','button'].includes(e.type)).map(e => [fieldName(e), e.type === 'file' ? [...e.files].map(f => [f.name,f.size,f.lastModified]) : e.type === 'checkbox' || e.type === 'radio' ? e.checked : e.multiple ? [...e.selectedOptions].map(o => o.value) : e.value]));
const visible = el => el.isConnected && el.getClientRects().length > 0;
export const isTopSurface = el => [...stack].reverse().find(s => visible(s.el))?.el === el;
export const surfaceFor = target => [...stack].reverse().find(s => visible(s.el) && s.el.contains(target))?.el;
export function guarded() { return stack.some(s => visible(s.el)) || [...forms].some(([el,f]) => visible(el) && f.dirty()); }
export function publishNavigation() { window.dispatchEvent(new Event('miso-navigation-state')); }
export function addSurface(el, close) {
    const entry = {el, close}; stack.push(entry); publishNavigation();
    requestAnimationFrame(publishNavigation);
    return () => { const i = stack.indexOf(entry); if (i >= 0) stack.splice(i,1); publishNavigation(); };
}
export function confirmDiscard() {
    if (confirming) return Promise.resolve(false);
    confirming = true;
    return new Promise(resolve => {
        const event = new CustomEvent('miso-discard-request', {cancelable:true, detail:{resolve}});
        if (window.dispatchEvent(event)) resolve(false);
    }).finally(() => { confirming = false; });
}
export async function allowLeave(root = document) {
    const dirty = [...forms].filter(([el,f]) => (root === document || root.contains(el)) && visible(el) && f.dirty());
    if (!dirty.length) return true;
    if (!await confirmDiscard()) return false;
    dirty.forEach(([,f]) => f.reset());
    return true;
}
export async function handleBack() {
    const top = [...stack].reverse().find(s => visible(s.el));
    if (top) { top.close(); return true; }
    return !await allowLeave();
}
export function registerNavigationGuard(Alpine) {
    window.MisoNavigation = {guarded};
    Alpine.data('ltDiscardGuard', () => ({open:false, resolve:null,
        ask(event) { event.preventDefault(); this.resolve = event.detail.resolve; this.open = true; },
        finish(discard) { this.open = false; const done = this.resolve; this.resolve = null; done?.(discard); },
        destroy() { this.finish(false); }
    }));
    Alpine.directive('dirty-guard', (el, {expression, modifiers}, {evaluateLater, cleanup, effect}) => {
        let baseline = null;
        let loading = false;
        const snapshot = () => formSnapshot(el.querySelectorAll('input,select,textarea'));
        const state = {owner: null, submitting:false, dirty:() => baseline !== null && !loading && snapshot() !== baseline, reset:() => {baseline = snapshot(); publishNavigation();}};
        let node = el;
        while (node) {
            if (node.hasAttribute?.('wire:id')) { state.owner = node.getAttribute('wire:id'); break; }
            node = node._x_teleportBack ?? node.parentElement;
        }
        const setup = () => { if (visible(el) && !loading && baseline === null) state.reset(); };
        if (expression) {
            const read = evaluateLater(expression);
            effect(() => read(value => { loading = !!value; if (loading) baseline = null; else setTimeout(setup, 0); }));
        }
        const observer = new MutationObserver(() => { setup(); if (!visible(el)) baseline = null; });
        observer.observe(el.closest('.md-form-dialog-layer') ?? el, {attributes:true, childList:true, subtree:true});
        const changed = () => { setup(); publishNavigation(); };
        const submitted = () => { state.submitting = true; };
        const closing = async event => {
            event.stopImmediatePropagation();
            if (await allowLeave(el)) {
                const replay = new CustomEvent('md-surface-close', {bubbles:true, detail:{approved:true}});
                el.closest('[x-md-surface]')?.dispatchEvent(replay);
            }
        };
        const surface = el.closest('[x-md-surface]');
        const intercept = event => { if (surfaceFor(event.target) === surface && !event.detail?.approved) closing(event); };
        if (!modifiers.includes('external-close')) surface?.addEventListener('md-surface-close', intercept, true);
        el.addEventListener('input', changed); el.addEventListener('change', changed);
        el.addEventListener('submit', submitted);
        el.addEventListener('miso-form-saved', state.reset);
        forms.set(el,state); setTimeout(setup,0);
        cleanup(() => { observer.disconnect(); forms.delete(el); surface?.removeEventListener('md-surface-close',intercept,true); el.removeEventListener('input',changed); el.removeEventListener('change',changed); el.removeEventListener('submit',submitted); el.removeEventListener('miso-form-saved',state.reset); publishNavigation(); });
    });
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('commit', ({component, succeed, fail}) => {
            const related = [...forms.values()].filter(f => f.submitting && f.owner === component.id);
            if (!related.length) return;
            fail(() => related.forEach(f => {f.submitting = false;}));
            succeed(({snapshot}) => {
                related.forEach(f => { f.submitting = false; });
                const data = typeof snapshot === 'string' ? JSON.parse(snapshot) : snapshot;
                if (!Object.keys(data?.memo?.errors ?? {}).length) requestAnimationFrame(() => related.forEach(f => f.reset()));
            });
        });
    }, {once:true});
    let bypass = null;
    document.addEventListener('livewire:navigate', event => {
        if (bypass === event.detail.url.href) { bypass = null; return; }
        if (!guarded()) return;
        // A surface can be open but clean; navigation still needs dirty protection only.
        event.preventDefault();
        allowLeave().then(ok => { if (ok) { bypass = event.detail.url.href; window.Livewire.navigate(bypass); } });
    });
    document.addEventListener('click', async event => {
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('wire:navigate') || event.defaultPrevented || event.ctrlKey || event.metaKey || event.button !== 0 || !guarded()) return;
        const url = new URL(link.href, location.href);
        if (url.href === location.href || (url.pathname === location.pathname && url.search === location.search && url.hash)) return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (await allowLeave()) { if (link.target === '_blank') window.open(url.href,'_blank'); else location.assign(url.href); }
    }, true);
    document.addEventListener('submit', async event => {
        if (!event.target.matches('form[action]') || !guarded() || event.target.hasAttribute('data-leave-approved')) return;
        if (!new URL(event.target.action, location.href).pathname.endsWith('/logout')) return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (await allowLeave()) { event.target.dataset.leaveApproved = '1'; event.target.requestSubmit(); }
    }, true);
    window.addEventListener('beforeunload', event => {
        if ([...forms].some(([el,f]) => visible(el) && f.dirty())) { event.preventDefault(); event.returnValue = ''; }
    });
}
