@props(['locked' => true])

@if ($locked)
    <i {{ $attributes->class(['bi bi-lock-fill task-locked-icon']) }} role="img" aria-label="Horario fijo" title="Horario fijo"></i>
@endif
