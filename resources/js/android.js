import {guarded, handleBack, allowLeave} from './navigation-guard.js';
const pending = new Map();
let serial = 0;
let context = () => JSON.parse(document.querySelector('meta[name="miso-android"]')?.content ?? '{}');
export const isAndroid = () => !!window.MisoNative?.postMessage;
export function nativeRequest(type, data = {}) {
    if (!isAndroid()) return Promise.reject(new Error('Android no disponible'));
    const id = String(++serial);
    return new Promise((resolve,reject) => {
        const timeout = setTimeout(() => { pending.delete(id); reject(new Error('Android no respondió')); }, type === 'pushEnable' ? 120000 : 15000);
        pending.set(id,{resolve: value => {clearTimeout(timeout);resolve(value);},reject});
        window.MisoNative.postMessage(JSON.stringify({v:1,type,id,...data}));
    });
}
async function api(action, identity, extra = {}) {
    const url = context()[action];
    if (!url || new URL(url,location.href).origin !== location.origin) throw new Error('Destino no válido');
    const response = await fetch(url,{method: action === 'revoke' ? 'DELETE' : 'POST',credentials:'same-origin',
        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content ?? ''},
        body:JSON.stringify({installation_id:identity.installation_id,secret:identity.secret,...extra})});
    if (!response.ok) throw new Error('No pudimos conectar los avisos con tu cuenta.');
    return response.json();
}
export async function androidPush(action) {
    const identity = await nativeRequest('pushStatus');
    if (action === 'disable') {
        // Stop local display first, even if the revocation request fails.
        await nativeRequest('pushDisable');
        await api('revoke',identity);
        return 'off';
    }
    if (action === 'enable') {
        const permitted = await nativeRequest('pushEnable');
        if (permitted.state !== 'ready') return permitted.state;
        const registered = await api('register',permitted,{token:permitted.token});
        await nativeRequest('pushBind',{binding:registered.binding});
        window.Livewire?.dispatch('android-device-updated');
        return 'on';
    }
    const server = await api('status',identity);
    if (!context().account) { await nativeRequest('pushDisable'); return 'off'; }
    if (!server.configured || identity.state === 'unconfigured') return 'unconfigured';
    if (['denied','blocked','unsupported'].includes(identity.state)) return identity.state;
    if (!server.active || !identity.enabled) {
        if (server.active && !identity.enabled) await api('revoke',identity);
        await nativeRequest('pushDisable');
        return 'off';
    }
    // Also resynchronizes tokens rotated while the app was closed.
    if (identity.token_dirty || identity.binding !== server.binding) {
        const refreshed = await api('register',identity,{token:identity.token});
        await nativeRequest('pushBind',{binding:refreshed.binding});
    }
    return 'on';
}
export function registerAndroid() {
    if (!isAndroid()) return;
    window.MisoAndroid = {
        receive(message) {
            if (message.v !== 1) return;
            if (message.id && pending.has(message.id)) { const p = pending.get(message.id); pending.delete(message.id); p.resolve(message); }
            if (message.type === 'notification') window.Livewire?.dispatch('android-notification-received');
            if (message.type === 'resume') sync(true);
        },
        async back(id) {
            const handled = await handleBack();
            window.MisoNative.postMessage(JSON.stringify({v:1,type:'backResult',id,handled}));
        },
        async openNotice(id) {
            if (!/^[a-f0-9-]{36}$/i.test(id) || !await allowLeave()) return;
            const base = context().notice;
            if (base && new URL(base,location.href).origin === location.origin) location.assign(base+'/'+encodeURIComponent(id));
        }
    };
    window.MisoNative.onmessage = event => { try { window.MisoAndroid.receive(JSON.parse(event.data)); } catch {} };
    const publish = () => window.MisoNative.postMessage(JSON.stringify({v:1,type:'navigation',guarded:guarded()}));
    let lastSync = 0;
    let lastAccount;
    const sync = (force = false) => {
        window.MisoNative.postMessage(JSON.stringify({v:1,type:'session',account:context().account ?? null}));
        publish();
        if (!force && lastAccount === context().account && Date.now() - lastSync < 30000) return;
        lastAccount = context().account;
        lastSync = Date.now();
        if (context().account) androidPush('status').then(state => window.dispatchEvent(new CustomEvent('android-push-state',{detail:{state}}))).catch(() => {});
    };
    window.addEventListener('miso-navigation-state',publish);
    document.addEventListener('livewire:navigated',() => sync());
    sync();
}
