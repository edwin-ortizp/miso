{{-- CHG-075: cabecera única de las tarjetas del Inicio (ícono en contenedor tonal, título, contador y acción). --}}
@props(['icon', 'title', 'tone' => 'primary', 'count' => null, 'href' => null, 'action' => null, 'hint' => null])

<div {{ $attributes->class(['home-card-head']) }}>
    <span class="home-card-head__icon home-tone--{{ $tone }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
    <div class="home-card-head__text">
        <h2 class="md-title-small mb-0">{{ $title }}</h2>
        @if ($hint)<p class="home-card-head__hint md-label-small mb-0">{{ $hint }}</p>@endif
    </div>
    @if ($count !== null)<span class="md-chip-tonal home-card-head__count home-tone--{{ $tone }}">{{ $count }}</span>@endif
    @if ($href && $action)<a href="{{ $href }}" class="md-btn-text home-card-head__action">{{ $action }}</a>@endif
</div>
