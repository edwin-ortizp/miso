@props(['code' => null, 'copy' => false])
@if ($code)
<span class="md-task-code md-body-small d-inline-flex flex-wrap align-items-center gap-1" style="color: var(--md-sys-color-on-surface-variant); max-width: 100%;" @if($copy) x-data="{ message: '' }" @endif>
    <code style="font-family: monospace; font-size: inherit; color: inherit; overflow-wrap: anywhere;">{{ $code }}</code>
    @if ($copy)
        <button type="button" class="md-btn-icon md-btn--sm" aria-label="Copiar código {{ $code }}" title="Copiar código"
                @click="(async () => { try { await navigator.clipboard.writeText(@js($code)); message = 'Código copiado'; } catch { message = 'No se pudo copiar el código'; } })()">
            <i class="bi bi-copy" aria-hidden="true"></i>
        </button>
        <span role="status" class="md-body-small" x-text="message"></span>
    @endif
</span>
@endif
