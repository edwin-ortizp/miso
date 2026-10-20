<div class="d-flex flex-column gap-2">
    <div class="row g-3">
        <div class="col-md-6"><div class="row g-2"><div class="col-7"><div class="md-text-field"><input type="date" wire:model.blur="{{ $startModel }}" placeholder=" " id="{{ $idPrefix }}-start"><label for="{{ $idPrefix }}-start">Fecha inicio</label></div></div><div class="col-5"><div class="md-text-field"><input type="time" wire:model.blur="{{ $startTimeModel }}" placeholder=" " id="{{ $idPrefix }}-start-time"><label for="{{ $idPrefix }}-start-time">Hora</label></div></div></div></div>
        <div class="col-md-6"><div class="row g-2"><div class="col-7"><div class="md-text-field"><input type="date" wire:model.blur="{{ $endModel }}" placeholder=" " id="{{ $idPrefix }}-end"><label for="{{ $idPrefix }}-end">Fecha fin</label>@error($endModel)<div class="md-supporting-text" role="alert" @if($localEditor ?? false) x-show="submitted" @endif style="color: var(--md-sys-color-error);">{{ $message }}</div>@enderror</div></div><div class="col-5"><div class="md-text-field"><input type="time" wire:model.blur="{{ $endTimeModel }}" placeholder=" " id="{{ $idPrefix }}-end-time"><label for="{{ $idPrefix }}-end-time">Hora</label></div></div></div></div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="md-body-small" style="color: var(--md-sys-color-on-surface-variant);">Estimar duración</span>
        @foreach ([15 => '15 min', 30 => '30 min', 60 => '1 hora', 120 => '2 horas'] as $minutes => $label)
            <button type="button" wire:click="{{ $durationAction }}({{ $minutes }})" class="md-chip md-chip-filter {{ ($estimatedTime ?? null) === $minutes ? 'selected' : '' }}">{{ $label }}</button>
        @endforeach
        @if ($estimatedTime ?? null)<span class="md-chip-tonal"><i class="bi bi-clock"></i> {{ $estimatedTime >= 60 ? intdiv($estimatedTime, 60).' h'.($estimatedTime % 60 ? ' '.($estimatedTime % 60).' min' : '') : $estimatedTime.' min' }}</span>@endif
    </div>
</div>
