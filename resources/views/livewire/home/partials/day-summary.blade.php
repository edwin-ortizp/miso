{{-- CHG-136: resumen del día. Solo usa datos que Mi día ya calcula; cada casilla es un atajo a su bloque. --}}
@php
    $liters = fn (float|int $ml): string => rtrim(rtrim(number_format($ml / 1000, 1, ',', '.'), '0'), ',');
    $summary = collect([
        $showModule('habits') ? [
            'href' => '#home-habits', 'icon' => 'bi-check2-square', 'tone' => 'success', 'label' => 'Hábitos',
            'value' => $completedHabits.'/'.$totalHabits, 'spoken' => $completedHabits.' de '.$totalHabits,
            'progress' => $totalHabits > 0 ? $completedHabits / $totalHabits : null,
        ] : null,
        $showModule('tasks') ? [
            'href' => '#home-tasks', 'icon' => 'bi-list-task', 'tone' => 'info', 'label' => 'Tareas',
            'value' => (string) $todayTaskCount, 'unit' => 'para hoy', 'spoken' => $todayTaskCount.' para hoy',
            'progress' => ($todayTaskCount + $completedTasksToday) > 0 ? $completedTasksToday / ($todayTaskCount + $completedTasksToday) : null,
        ] : null,
        $showModule('water') ? [
            'href' => '#home-quick', 'icon' => 'bi-droplet', 'tone' => 'primary', 'label' => 'Agua',
            'value' => $liters($waterTotal), 'unit' => '/ '.$liters($waterGoal).' L', 'spoken' => $liters($waterTotal).' de '.$liters($waterGoal).' litros',
            'progress' => $waterGoal > 0 ? min(1, $waterTotal / $waterGoal) : null,
        ] : null,
        $showModule('mood') ? [
            'href' => '#home-quick', 'icon' => 'bi-emoji-smile', 'tone' => 'tertiary', 'label' => 'Ánimo',
            'value' => $lastMood?->emoji ?? '—', 'unit' => $lastMood ? \Illuminate\Support\Str::limit($lastMood->text, 12) : 'sin registrar',
            'spoken' => $lastMood?->text ?? 'sin registrar', 'progress' => null,
        ] : null,
    ])->filter()->values();
@endphp
@if ($summary->isNotEmpty())
    <nav class="home-summary mb-3" aria-label="Tu día de un vistazo">
        @foreach ($summary as $tile)
            <a class="home-summary__tile" href="{{ $tile['href'] }}" aria-label="{{ $tile['label'] }}: {{ $tile['spoken'] }}">
                <span class="home-summary__label md-label-small"><i class="bi {{ $tile['icon'] }} home-tone-text--{{ $tile['tone'] }}" aria-hidden="true"></i> {{ $tile['label'] }}</span>
                <span class="home-summary__value"><strong>{{ $tile['value'] }}</strong>@isset($tile['unit'])<small>{{ $tile['unit'] }}</small>@endisset</span>
                <span class="home-summary__bar" aria-hidden="true">@if ($tile['progress'] !== null)<span class="home-summary__fill home-summary__fill--{{ $tile['tone'] }}" style="--md-progress-value: {{ round($tile['progress'] * 100) }}%;"></span>@endif</span>
            </a>
        @endforeach
    </nav>
@endif
