// Atajos de la barra del editor Markdown: una sola tabla para ejecutarlos y rotular los tooltips.
// `mod` = Ctrl (⌘ en Mac). La checklist (Ctrl+L) la ejecuta app.js; aquí solo se rotula.
export const markdownShortcuts = {
    bold: { key: 'b', mod: true },
    italic: { key: 'i', mod: true },
    link: { key: 'k', mod: true },
    checklist: { key: 'l', mod: true, external: true },
    copy: { key: 'c', alt: true, shift: true },
    expand: { key: 'f', mod: true, shift: true },
};

export function isMacPlatform(nav = globalThis.navigator) {
    const platform = nav?.userAgentData?.platform ?? nav?.platform ?? '';
    return /mac|iphone|ipad/i.test(platform);
}

/** Texto visible del atajo, p. ej. «Ctrl+B», «⌘+B» o «Alt+Shift+C». */
export function shortcutLabel(shortcut, mac = isMacPlatform()) {
    if (!shortcut) return '';
    return [
        shortcut.mod && (mac ? '⌘' : 'Ctrl'),
        shortcut.alt && (mac ? '⌥' : 'Alt'),
        shortcut.shift && 'Shift',
        shortcut.key.toUpperCase(),
    ].filter(Boolean).join('+');
}

/** Valor de aria-keyshortcuts, p. ej. «Control+B». */
export function shortcutAria(shortcut, mac = isMacPlatform()) {
    if (!shortcut) return '';
    return [
        shortcut.mod && (mac ? 'Meta' : 'Control'),
        shortcut.alt && 'Alt',
        shortcut.shift && 'Shift',
        shortcut.key.toUpperCase(),
    ].filter(Boolean).join('+');
}

/** Etiqueta con atajo: «Negrita (Ctrl+B)». */
export function withShortcut(label, action, mac = isMacPlatform()) {
    const shortcut = markdownShortcuts[action];
    return shortcut ? `${label} (${shortcutLabel(shortcut, mac)})` : label;
}

/** Acción que corresponde a un evento de teclado, o null. */
export function shortcutAction(event, mac = isMacPlatform()) {
    const key = (event.key ?? '').toLowerCase();
    const code = (event.code ?? '').toLowerCase();
    const mod = mac ? event.metaKey : event.ctrlKey;
    for (const [action, shortcut] of Object.entries(markdownShortcuts)) {
        if (shortcut.external) continue;
        // Con Alt el carácter cambia según el teclado (Alt+C → «ç» en Mac): se compara también el código físico.
        const keyMatches = key === shortcut.key || code === `key${shortcut.key}`;
        if (keyMatches && !!shortcut.mod === !!mod && !!shortcut.alt === event.altKey && !!shortcut.shift === event.shiftKey) {
            return action;
        }
    }

    return null;
}
