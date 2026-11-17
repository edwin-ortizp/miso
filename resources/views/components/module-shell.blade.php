@props(['module' => null, 'title' => null, 'subtitle' => null, 'icon' => null, 'archetype' => null, 'tabs' => null, 'back' => null, 'activeRoute' => null, 'navigationQuery' => null])

@php
    use App\Support\Ui\ScreenArchetype;

    $routeName = $activeRoute ?? request()->route()?->getName() ?? '';
    $moduleKey = $module;

    if (! $moduleKey) {
        foreach (config('modules', []) as $key => $candidate) {
            foreach ($candidate['patterns'] ?? [] as $pattern) {
                if (\Illuminate\Support\Str::is($pattern, $routeName)) {
                    $moduleKey = $key;
                    break 2;
                }
            }
        }
    }

    $definition = config("modules.{$moduleKey}", []);
    $screenArchetype = ScreenArchetype::resolve($archetype, $definition, $routeName);
    $archetypeSource = ScreenArchetype::isDeclared($archetype, $definition, $routeName) ? 'declared' : 'fallback';
    $moduleTitle = $title ?? $definition['title'] ?? 'MiSo';
    // El panel contextual se retiró en la v1; los módulos que lo recuperan lo declaran.
    $showRail = ($definition['rail'] ?? false) && (! isset($definition['rail_routes']) || \Illuminate\Support\Str::is($definition['rail_routes'], $routeName));
    // Un detalle declara sus propias pestañas; reemplazan a las del módulo en esa pantalla.
    $isDetail = ! empty($tabs);
    // CHG-139: las funciones que la persona apagó no aparecen como pestañas.
    $navigationTabs = $isDetail ? $tabs : app(\App\Services\Experience\PersonalExperience::class)->tabs($moduleKey, $definition['tabs'] ?? []);
@endphp

<section {{ $attributes->class(['md-module-shell', 'md-archetype--'.$screenArchetype]) }}
         data-module="{{ $moduleKey }}"
         data-archetype="{{ $screenArchetype }}"
         data-archetype-source="{{ $archetypeSource }}">
    {{-- Sin banner: la identidad vive en la barra superior; el encabezado se conserva para lectores de pantalla. --}}
    <h1 class="visually-hidden" data-region="identity">{{ $moduleTitle }}</h1>
    @if ($title)
        @push('module-title'){{ $title }}@endpush
    @endif
    {{-- Detalle sin pestañas: el regreso va en la barra superior, antes del ícono del módulo. --}}
    @if ($back && ! $isDetail)
        @push('module-back')<a href="{{ $back['href'] }}" @if ($back['navigate'] ?? true) wire:navigate @endif class="md-btn-icon lt-topbar__back" aria-label="{{ $back['label'] }}" title="{{ $back['label'] }}"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>@endpush
    @endif
    @isset($actions)
        <div class="md-module-actions" data-region="actions">{{ $actions }}</div>
    @endisset

    @if (! empty($navigationTabs))
        <div data-region="navigation">
            <x-module-tabs :tabs="$navigationTabs" :route-name="$routeName" :navigation-query="$navigationQuery"
                           :preserve="$isDetail ? [] : ($definition['preserve'] ?? [])"
                           :back="$isDetail ? $back : null"
                           :label="$isDetail ? 'Secciones de '.$moduleTitle : 'Vistas del módulo'" />
        </div>
    @endif

    @isset($controls)
        <div class="md-module-controls" data-region="controls">{{ $controls }}</div>
    @endisset

    <div class="md-module-content">
        @isset($rail)
            {{-- El arquetipo resuelve su propia composición: contenido y contexto en paralelo
                 desde 1200 px y en una sola columna por debajo, sin utilidades externas. --}}
            <div @class(['md-module-workspace', 'md-module-workspace--rail' => $showRail])>
                @isset($contextBefore){{ $contextBefore }}@endisset
                <div class="md-module-primary" data-region="content">{{ $slot }}</div>
                <aside class="md-context-rail" data-region="context" aria-label="Contexto del módulo">{{ $rail }}</aside>
            </div>
        @else
            <div class="md-module-primary" data-region="content">{{ $slot }}</div>
        @endisset
    </div>
</section>
