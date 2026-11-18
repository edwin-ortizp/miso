<div class="learning-shortcuts">
    <x-context-widget title="Accesos directos" icon="bi-pin-angle">
        @if ($shortcuts)
            <ul class="learning-shortcuts__list">
                @foreach ($shortcuts as $shortcut)
                    <li wire:key="pin-{{ $shortcut['type'] }}-{{ $shortcut['id'] }}">
                        <a href="{{ $shortcut['href'] }}" wire:navigate><strong>{{ $shortcut['title'] }}</strong><small>{{ $shortcut['label'] }} · {{ $shortcut['origin'] }}</small></a>
                        <button type="button" class="md-btn-icon" wire:click="setPin('{{ $shortcut['type'] }}', '{{ $shortcut['id'] }}', false)" aria-label="Quitar de accesos directos: {{ $shortcut['title'] }}" title="Quitar de accesos directos"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i></button>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="md-body-small">Fija una formación o cualquier contenido desde su menú para abrirlo directamente.</p>
        @endif
    </x-context-widget>
</div>
