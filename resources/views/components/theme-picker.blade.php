@props(['variant' => 'card'])

@php
    $options = [
        'light' => ['label' => 'Claro', 'icon' => 'bi-sun'],
        'dark' => ['label' => 'Oscuro', 'icon' => 'bi-moon-stars'],
        'system' => ['label' => $variant === 'menu' ? 'Sistema' : 'Igual que mi dispositivo', 'icon' => 'bi-circle-half'],
    ];
@endphp

<div x-data="themePicker" data-url="{{ route('appearance.update') }}" role="radiogroup"
     aria-label="Apariencia" {{ $attributes->class(['theme-picker', 'theme-picker--'.$variant]) }}>
    @foreach ($options as $value => $option)
        <button type="button" role="radio" class="theme-picker__option"
                :aria-checked="(preference === '{{ $value }}').toString()"
                :tabindex="preference === '{{ $value }}' ? 0 : -1"
                @click="choose('{{ $value }}')"
                @keydown.arrow-right.prevent="move($event, 1)" @keydown.arrow-down.prevent="move($event, 1)"
                @keydown.arrow-left.prevent="move($event, -1)" @keydown.arrow-up.prevent="move($event, -1)">
            <i class="bi {{ $option['icon'] }}" aria-hidden="true"></i>
            <span>{{ $option['label'] }}</span>
            <i class="bi bi-check-lg theme-picker__check" aria-hidden="true" x-show="preference === '{{ $value }}'"></i>
        </button>
    @endforeach
    <p class="theme-picker__status" :class="{ 'theme-picker__status--error': failed }" role="status" aria-live="polite" x-text="announcement"></p>
</div>
