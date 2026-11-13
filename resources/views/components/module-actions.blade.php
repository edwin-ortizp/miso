@props([
    'primary',
    'secondary' => [],
    // Obsoletos: la acción principal siempre es el FAB canónico (x-ui.fab).
    'mobileStyle' => 'fab',
    'fabAlways' => true,
    // FAB dividido cuando hay varias acciones de creación.
    'split' => false,
    // Chat del compañero (CHG-100): acciones rápidas (compose / tap).
    'quick' => [],
    'greeting' => null,
])

@php
    /*
     * Acciones de un módulo.
     * - `primary` es la acción de crear/registrar/agregar y se renderiza con x-ui.fab.
     * - Las secundarias con `'create' => true` se despliegan desde el mismo FAB (speed dial).
     * - El resto (navegación, archivar, marcar…) va al menú ⋮ del encabezado.
     */
    $secondary = collect($secondary)->filter()->values();
    $createActions = $secondary->filter(fn ($action) => ! empty($action['create']))->values()->all();
    $menuActions = $secondary->reject(fn ($action) => ! empty($action['create']))->values();

    $renderMenuItem = static function (array $action): string {
        $label = e($action['label']);
        $icon = e($action['icon'] ?? 'bi-dot');
        $content = '<i class="bi '.$icon.'" aria-hidden="true"></i><span>'.$label.'</span>';
        $class = 'md-mobile-action-menu__item';

        if (! empty($action['href'])) {
            return '<a href="'.e($action['href']).'" class="'.$class.'" role="menuitem">'.$content.'</a>';
        }

        $binding = 'wire:click="'.e($action['action'] ?? '').'"';
        if (! empty($action['event'])) {
            $expression = '$dispatch('.json_encode($action['event']).', '.json_encode($action['detail'] ?? new \stdClass).')';
            $binding = 'x-on:click="'.e($expression).'"';
        }

        return '<button type="button" '.$binding.' class="'.$class.'" role="menuitem">'.$content.'</button>';
    };
@endphp

@if ($menuActions->isNotEmpty())
    <div class="md-responsive-actions md-responsive-actions--fab-always" x-data="{ secondaryOpen: false }">
        <div class="md-mobile-action-menu">
            <button type="button" class="md-btn-icon md-mobile-action-menu__trigger"
                    @click="secondaryOpen = !secondaryOpen"
                    @click.outside="secondaryOpen = false"
                    :aria-expanded="secondaryOpen"
                    aria-haspopup="menu"
                    aria-label="Más acciones">
                <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
            </button>
            <div class="md-mobile-action-menu__surface" x-cloak x-show="secondaryOpen" x-transition.origin.top.right role="menu">
                @foreach ($menuActions as $action)
                    {!! $renderMenuItem($action) !!}
                @endforeach
            </div>
        </div>
    </div>
@endif

<x-ui.fab :label="$primary['label']" :icon="$primary['icon'] ?? 'bi-plus-lg'"
          :action="$primary['action'] ?? null" :href="$primary['href'] ?? null"
          :event="$primary['event'] ?? null" :detail="$primary['detail'] ?? null"
          :actions="$createActions" :split="$split" :quick="$quick" :greeting="$greeting" />
