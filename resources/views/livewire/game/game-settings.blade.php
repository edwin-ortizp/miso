<x-module-shell module="game" archetype="settings">
    @if ($message)
        <x-ui.snackbar>{{ $message }}</x-ui.snackbar>
    @endif
    <div class="settings-layout">
        <form wire:submit="save" x-dirty-guard x-data="{ changed: false }" @miso-form-state="changed = $event.detail.dirty"
              @miso-form-discard="$event.detail.waitUntil($wire.restoreSavedSettings().then(() => $nextTick()))" class="settings-content" wire:key="game-settings-{{ $section }}">
            @include('livewire.game.partials.settings.'.$section)
            <div class="game-settings-save">
                <x-ui.action type="submit" variant="filled" icon="bi-check-lg" wire:loading.attr="disabled" wire:target="save">Guardar cambios</x-ui.action>
                <span x-show="changed" x-cloak class="md-body-small" role="status">Cambios pendientes</span>
                <span wire:loading wire:target="save" role="status">Guardando…</span>
            </div>
        </form>
        <aside class="settings-aside">
            <nav class="settings-nav" aria-label="Secciones de ajustes de Colección">
                @foreach ($sections as $key => [$label, $icon])
                    <a href="{{ route($key === 'general' ? 'game.settings' : 'game.settings.'.$key) }}" wire:navigate
                       @class(['settings-nav__link', 'is-active' => $section === $key])
                       @if ($section === $key) aria-current="page" @endif>
                        <i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>
    </div>
</x-module-shell>
