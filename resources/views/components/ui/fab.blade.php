@props([
    'label',
    'icon' => 'bi-plus-lg',
    'action' => null,
    'href' => null,
    'event' => null,
    'detail' => null,
    'actions' => [],
    'module' => null,
    'split' => false,
    'compact' => false,
])

@php
    /*
     * FAB canónico de creación (Material Design 3).
     * - Escritorio: extendido (ícono + texto). Móvil: solo ícono.
     * - Con `actions`: menú FAB (speed dial) con la acción principal y las secundarias.
     * - Con `actions` y `split`: FAB dividido; el segmento principal ejecuta la acción y el chevron despliega las demás.
     * - Con `actions` y `compact`: el disparador del menú es solo un ícono «+» (sin texto) para ahorrar espacio.
     * - Se teletransporta a <body> para quedar sobre acordeones, menús y paneles.
     */
    $actions = collect($actions)->filter()->values();
    $moduleKey = \App\Support\Ui\ModuleContext::current($module);
    $primary = ['label' => $label, 'icon' => $icon, 'action' => $action, 'href' => $href, 'event' => $event, 'detail' => $detail];

    $binding = static function (array $item): string {
        if (! empty($item['href'])) {
            return 'href="'.e($item['href']).'"';
        }
        if (! empty($item['event'])) {
            $expression = '$dispatch('.json_encode($item['event']).', '.json_encode($item['detail'] ?? new \stdClass).')';

            return 'x-on:click="'.e($expression).'"';
        }

        return 'wire:click="'.e($item['action'] ?? '').'"';
    };
    $menuId = 'fab-menu-'.\Illuminate\Support\Str::random(6);
    $tag = static fn (array $item): string => empty($item['href']) ? 'button' : 'a';
@endphp

@teleport('body')
    <div class="md-fab-layer md-fab-host" data-module="{{ $moduleKey }}"
         @if ($actions->isNotEmpty()) x-data="{ fabOpen: false }" @keydown.escape.window="fabOpen = false" @click.outside="fabOpen = false" :class="{ 'is-open': fabOpen }" @endif>
        @if ($actions->isEmpty())
            <{{ $tag($primary) }} @if ($tag($primary) === 'button') type="button" @endif {!! $binding($primary) !!}
                class="md-fab md-fab-extended md-create-fab md-module-primary-fab" aria-label="{{ $label }}" title="{{ $label }}">
                <i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
            </{{ $tag($primary) }}>
        @elseif ($split)
            <div class="md-create-fab__menu" id="{{ $menuId }}" x-show="fabOpen" x-cloak x-transition.origin.bottom.right role="menu" aria-label="{{ $label }}">
                <{{ $tag($primary) }} @if ($tag($primary) === 'button') type="button" @endif {!! $binding($primary) !!}
                    class="md-create-fab__item md-create-fab__item--primary" role="menuitem" @click="fabOpen = false">
                    <span>{{ $label }}</span><i class="bi {{ $icon }}" aria-hidden="true"></i>
                </{{ $tag($primary) }}>
                @foreach ($actions as $item)
                    <{{ $tag($item) }} @if ($tag($item) === 'button') type="button" @endif {!! $binding($item) !!}
                        class="md-create-fab__item" role="menuitem" @click="fabOpen = false">
                        <span>{{ $item['label'] }}</span><i class="bi {{ $item['icon'] ?? 'bi-plus-lg' }}" aria-hidden="true"></i>
                    </{{ $tag($item) }}>
                @endforeach
            </div>
            <div class="md-fab-split">
                <{{ $tag($primary) }} @if ($tag($primary) === 'button') type="button" @endif {!! $binding($primary) !!}
                    class="md-fab md-fab-extended md-create-fab md-fab-split__main" aria-label="{{ $label }}" title="{{ $label }}">
                    <i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                </{{ $tag($primary) }}>
                <button type="button" class="md-fab md-create-fab md-fab-split__toggle" @click="fabOpen = !fabOpen"
                        :aria-expanded="fabOpen.toString()" aria-haspopup="menu" aria-controls="{{ $menuId }}" aria-label="Más acciones de creación" title="Más acciones de creación">
                    <i class="bi bi-chevron-up" aria-hidden="true"></i>
                </button>
            </div>
            {{-- Móvil: sin split, un único FAB que despliega todas las opciones hacia arriba. --}}
            <button type="button" class="md-fab md-fab-extended md-create-fab md-fab-split__compact" @click="fabOpen = !fabOpen"
                    :aria-expanded="fabOpen.toString()" aria-haspopup="menu" aria-controls="{{ $menuId }}" aria-label="{{ $label }}" title="{{ $label }}">
                <i class="bi" :class="fabOpen ? 'bi-x-lg' : @js($icon)" aria-hidden="true"></i><span>{{ $label }}</span>
            </button>
        @else
            <div class="md-create-fab__menu" x-show="fabOpen" x-cloak x-transition.origin.bottom.right role="menu" aria-label="{{ $label }}">
                @foreach ($actions->prepend($primary) as $item)
                    <{{ $tag($item) }} @if ($tag($item) === 'button') type="button" @endif {!! $binding($item) !!}
                        class="md-create-fab__item" role="menuitem" @click="fabOpen = false">
                        <span>{{ $item['label'] }}</span><i class="bi {{ $item['icon'] ?? 'bi-plus-lg' }}" aria-hidden="true"></i>
                    </{{ $tag($item) }}>
                @endforeach
            </div>
            <button type="button" class="md-fab {{ $compact ? '' : 'md-fab-extended' }} md-create-fab md-module-primary-fab" @click="fabOpen = !fabOpen"
                    :aria-expanded="fabOpen.toString()" aria-haspopup="menu" aria-label="{{ $compact ? 'Crear' : $label }}" title="{{ $compact ? 'Crear' : $label }}">
                <i class="bi" :class="fabOpen ? 'bi-x-lg' : @js($icon)" aria-hidden="true"></i>@unless ($compact)<span>{{ $label }}</span>@endunless
            </button>
        @endif
    </div>
@endteleport
