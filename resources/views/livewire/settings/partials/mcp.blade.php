@php
    $modules = \App\Mcp\Support\ToolCatalog::MODULES;
@endphp

<x-ui.section title="Servidor MCP"
              description="Conecta tu asistente de IA (Claude, ChatGPT…) con MiSo mediante Model Context Protocol."
              :level="2">
    <x-ui.card variant="outlined" icon="bi-robot" iconTone="secondary">
        <div class="settings-copy" x-data="{ copied: false, text: @js($mcpUrl) }">
            <code class="md-secret__value">{{ $mcpUrl }}</code>
            <x-ui.action variant="tonal" icon="bi-clipboard"
                         x-on:click="navigator.clipboard.writeText(text); copied = true; setTimeout(() => copied = false, 2000)">
                <span x-text="copied ? 'Copiada' : 'Copiar'">Copiar</span>
            </x-ui.action>
        </div>
        <p class="md-body-small">Agrégala como conector personalizado en tu cliente de IA y autoriza el acceso por OAuth, o usa un token de IA.</p>
        <dl class="md-context-list">
            <div><dt>Transporte</dt><dd>HTTP (streamable)</dd></div>
            <div><dt>Autenticación</dt><dd>OAuth 2.1 o token lt_ai_…</dd></div>
            <div><dt>Límite</dt><dd>60 solicitudes por minuto</dd></div>
            <div><dt>Herramientas</dt><dd>{{ $toolTotal }}</dd></div>
            <div><dt>Prompts</dt><dd>{{ $promptCount ?: 'Ninguno todavía' }}</dd></div>
            <div><dt>Recursos</dt><dd>{{ $resourceCount ?: 'Ninguno todavía' }}</dd></div>
        </dl>
        <details class="settings-details">
            <summary><i class="bi bi-file-text" aria-hidden="true"></i> Instrucciones del servidor</summary>
            <div class="settings-details__body">{{ $instructions }}</div>
        </details>
    </x-ui.card>
</x-ui.section>

<x-ui.management-card id="mcp-tools" title="Herramientas disponibles" icon="bi-tools"
                      :count="'('.$tools->count().' / '.$toolTotal.')'"
                      search="toolSearch" search-placeholder="Buscar herramientas" clear="clearToolFilters"
                      :active-filters="($toolKind !== '' ? 1 : 0) + ($toolModule !== '' ? 1 : 0)" noun="herramientas">
    <x-slot:filters>
        <div class="md-chip-rail md-mcard__filter-chips" role="group" aria-label="Tipo de herramienta">
            @foreach (['' => 'Todas', 'read' => 'Lectura', 'write' => 'Escritura'] as $value => $label)
                <x-ui.chip variant="filter" :selected="$toolKind === $value" wire:click="$set('toolKind', '{{ $value }}')">{{ $label }}</x-ui.chip>
            @endforeach
        </div>
        <x-ui.select name="toolModule" label="Módulo" placeholder="Todos los módulos" wire:model.live="toolModule"
                     :options="collect($modules)->mapWithKeys(fn ($module, $key) => [$key => $module[0].' ('.($moduleCounts[$key] ?? 0).')'])->all()" />
    </x-slot:filters>
    <x-slot:filterActions>
        <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearToolFilters">Limpiar</x-ui.action>
    </x-slot:filterActions>
    @php
        $toolFilters = array_values(array_filter([
            $toolKind !== '' ? ['key' => 'toolKind', 'value' => null, 'label' => 'Tipo: '.($toolKind === 'read' ? 'Lectura' : 'Escritura'), 'icon' => 'bi-pencil-square'] : null,
            $toolModule !== '' ? ['key' => 'toolModule', 'value' => null, 'label' => 'Módulo: '.($modules[$toolModule][0] ?? $toolModule), 'icon' => 'bi-grid'] : null,
        ]));
    @endphp
    @if ($toolFilters !== [])
        <x-slot:strip>
            <x-ui.applied-filters :filters="$toolFilters" remove="removeToolFilter" />
        </x-slot:strip>
    @endif

    @if ($tools->isEmpty())
        <x-ui.state variant="empty" title="Ninguna herramienta coincide" message="Prueba con otro texto o limpia los filtros." icon="bi-search" />
    @else
        <table class="md-table md-table--stack" aria-label="Herramientas del servidor MCP">
            <thead><tr><th scope="col">Herramienta</th><th scope="col">Módulo</th><th scope="col">Tipo</th><th scope="col">Descripción</th></tr></thead>
            <tbody>
                @foreach ($tools as $item)
                    <tr wire:key="tool-{{ $item['name'] }}">
                        <td data-label="Herramienta" class="md-table__title">
                            <x-ui.action variant="text" class="settings-toolname" wire:click="showTool('{{ $item['name'] }}')" aria-haspopup="dialog">
                                <code>{{ $item['name'] }}</code>
                            </x-ui.action>
                        </td>
                        <td data-label="Módulo" class="md-table__nowrap"><x-ui.chip :icon="$item['moduleIcon']">{{ $item['moduleLabel'] }}</x-ui.chip></td>
                        <td data-label="Tipo" class="md-table__nowrap">
                            <x-ui.chip variant="tonal" :tone="$item['readOnly'] ? 'info' : 'warning'">{{ $item['readOnly'] ? 'Lectura' : 'Escritura' }}</x-ui.chip>
                        </td>
                        <td data-label="Descripción" class="md-table__notes" title="{{ $item['description'] }}">{{ $item['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ui.management-card>

@if ($toolOpen && $tool)
{{-- Se teletransporta al body: el view-transition-name de <main> crea un contexto de apilamiento bajo el menú lateral. --}}
<template x-teleport="body">
<div x-data="{ open: true }" x-init="$watch('open', value => value || $wire.closeTool())" wire:key="tool-detail-{{ $tool['name'] }}">
    <x-ui.dialog state="open" title="Detalle de la herramienta" icon="bi-tools" size="lg" x-on:md-surface-close="$wire.closeTool()">
            <div class="settings-tool">
                <code class="settings-tool__name">{{ $tool['name'] }}</code>
                <div class="md-chip-rail">
                    <x-ui.chip :icon="$tool['moduleIcon']">{{ $tool['moduleLabel'] }}</x-ui.chip>
                    <x-ui.chip variant="tonal" :tone="$tool['readOnly'] ? 'info' : 'warning'">{{ $tool['readOnly'] ? 'Lectura' : 'Escritura' }}</x-ui.chip>
                </div>
                <dl class="md-context-list"><div><dt>Clase</dt><dd class="md-secret__value">{{ $tool['class'] }}</dd></div></dl>

                <h3 class="md-title-small mb-0">Descripción</h3>
                <p class="settings-tool__text">{{ $tool['description'] }}</p>

                <h3 class="md-title-small mb-0">Parámetros</h3>
                @if ($tool['parameters'] === [])
                    <p class="md-body-small mb-0">No recibe parámetros.</p>
                @else
                    <ul class="settings-params">
                        @foreach ($tool['parameters'] as $param)
                            <li>
                                <div class="settings-params__head">
                                    <code>{{ $param['name'] }}</code>
                                    <span class="md-body-small">{{ $param['type'] }}</span>
                                    @if ($param['required'])<x-ui.chip variant="tonal" tone="danger">Requerido</x-ui.chip>@endif
                                </div>
                                @if ($param['description'])<p class="md-body-small mb-0">{{ $param['description'] }}</p>@endif
                                @if ($param['enum'])<p class="md-body-small mb-0">Valores: {{ implode(', ', $param['enum']) }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        <x-slot:actions><x-ui.action wire:click="closeTool">Cerrar</x-ui.action></x-slot:actions>
    </x-ui.dialog>
</div>
</template>
@endif
