@props([
    'favorite' => false,
    'label',
])

@php
    abort_unless(filled($label), 500, 'Un favorito debe declarar el nombre del elemento.');
    $name = $favorite ? 'Quitar '.$label.' de favoritos' : 'Marcar '.$label.' como favorito';
@endphp

{{-- Estrella para marcar un elemento de un catálogo como favorito personal. La acción se enlaza con wire:click. --}}
<button type="button" {{ $attributes->class(['md-btn-icon', 'md-favorite-toggle', 'is-on' => $favorite]) }}
        aria-pressed="{{ $favorite ? 'true' : 'false' }}" aria-label="{{ $name }}" title="{{ $name }}">
    <i class="bi {{ $favorite ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>
</button>
