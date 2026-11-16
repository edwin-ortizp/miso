<x-module-shell module="water">
    <x-slot:controls>
        <div class="md-chip-group" aria-label="Período de análisis">
            @foreach ([30 => '30 días', 90 => '90 días', 365 => '1 año'] as $value => $label)
                <button wire:click="$set('period', {{ $value }})" class="md-chip md-chip-filter {{ $period === $value ? 'selected' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
    </x-slot:controls>
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6"><div class="md-card-filled h-100"><span class="md-label-small">PROMEDIO</span><div class="md-title-large mt-2">{{ number_format($rangeData['average']) }}</div><span class="md-body-small">ml/día</span></div></div>
        <div class="col-md-3 col-6"><div class="md-card-filled h-100"><span class="md-label-small">REGISTRO</span><div class="md-title-large mt-2">{{ $rangeData['tracked_days'] }}/{{ $period }}</div><span class="md-body-small">días</span></div></div>
        <div class="col-md-3 col-6"><div class="md-card-filled h-100"><span class="md-label-small">META</span><div class="md-title-large mt-2">{{ $rangeData['completed_days'] }}</div><span class="md-body-small">días cumplidos</span></div></div>
        <div class="col-md-3 col-6"><div class="md-card-filled h-100"><span class="md-label-small">MEJOR DÍA</span><div class="md-title-large mt-2">{{ number_format($rangeData['best']['total'] ?? 0) }}</div><span class="md-body-small">ml</span></div></div>
    </div>
    <section class="md-card-elevated water-range-chart" aria-label="Serie de hidratación">
        @php $max = max($rangeData['series']->max('total') ?? 1, $goal); @endphp
        @foreach ($rangeData['series'] as $day)<i style="height: {{ max(2, ($day['total'] / $max) * 100) }}%" title="{{ $day['date']->translatedFormat('d M') }}: {{ $day['total'] }} ml"></i>@endforeach
    </section>
</x-module-shell>
