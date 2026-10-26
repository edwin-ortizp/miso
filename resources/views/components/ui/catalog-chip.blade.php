@props(['color' => null, 'tone' => 'neutral', 'icon' => null])
@php
    $color = array_key_exists($color ?? '', \App\Support\Ui\CatalogColors::OPTIONS) ? $color : null;
    $tone = in_array($tone, ['neutral', 'primary', 'success', 'warning', 'danger', 'info'], true) ? $tone : 'neutral';
    $suffix = $tone === 'danger' ? 'error' : $tone;
@endphp
<span {{ $attributes->class(['md-chip-tonal', 'md-catalog-chip', 'md-catalog-chip--'.$color => $color !== null, 'md-chip-tonal--'.$suffix => $color === null && $tone !== 'neutral']) }}>
    @if ($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
    {{ $slot }}
</span>
