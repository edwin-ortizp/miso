@props([
    'state',
    'title',
    'placement' => 'bottom',
    'describedBy' => null,
    'persistent' => false,
])

@php
    abort_unless(in_array($placement, ['bottom', 'side'], true), 500, "Posición de hoja no soportada: {$placement}");

    $headingId = 'sheet-'.\Illuminate\Support\Str::slug($title);
@endphp

{{-- CHG-106: la hoja se teletransporta a <body> como los diálogos. Dentro de <main> quedaba atrapada en su
     contexto de apilamiento (view-transition-name) y la barra inferior, la superior y el compañero la tapaban. --}}
@teleport('body')
@if ($persistent)
<div x-show="{{ $state }}" x-cloak data-module="{{ \App\Support\Ui\ModuleContext::current() }}">
@else
<template x-if="{{ $state }}">
@endif
    <div @unless ($persistent) data-module="{{ \App\Support\Ui\ModuleContext::current() }}" @endunless>
        <div class="md-sheet-scrim" @click="{{ $state }} = false"></div>
        <div {{ $attributes->class(['md-sheet', 'md-sheet--side' => $placement === 'side']) }}
             role="dialog"
             aria-modal="true"
             aria-labelledby="{{ $headingId }}"
             @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
             x-md-surface="{{ $persistent ? $state : '' }}"
             @md-surface-close="{{ $state }} = false"
             @click.stop>
            <span class="md-sheet__handle" aria-hidden="true"></span>

            <div class="md-sheet__header">
                <h2 id="{{ $headingId }}" class="md-title-large">{{ $title }}</h2>
                <x-ui.icon-action icon="bi-x-lg" label="Cerrar" x-on:click="{{ $state }} = false" />
            </div>

            <div class="md-sheet__body">{{ $slot }}</div>

            @isset($actions)
                <div class="md-sheet__actions">{{ $actions }}</div>
            @endisset
        </div>
    </div>
@if ($persistent)
</div>
@else
</template>
@endif
@endteleport
