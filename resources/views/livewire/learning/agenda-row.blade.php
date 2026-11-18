@php
    use App\Services\Learning\StudyAgenda;
    $moment = StudyAgenda::moment($row);
    $past = StudyAgenda::overdue($row);
    $task = $row->kind === 'task';
@endphp
<li class="learning-agenda-row" wire:key="study-{{ $row->kind }}-{{ $row->id }}">
    <span class="learning-agenda-glyph" aria-hidden="true"><i class="bi {{ $task ? 'bi-list-task' : 'bi-journal-check' }}"></i></span>
    <div class="learning-agenda-description">
        <span class="learning-agenda-meta">{{ StudyAgenda::type($row->kind) }}@if($showSubject ?? false) · {{ $row->subject }}@endif</span>
        @if ($task)
            <x-ui.action variant="text" class="learning-agenda-link"
                x-on:click="$dispatch('task-editor', {action: 'openForm', id: '{{ $row->id }}'})">{{ $row->title }}</x-ui.action>
        @else
            <a class="learning-agenda-link"
                href="{{ route('learning.units.show', [$row->formation_id, $row->unit_id, 'section' => 'grades', 'evaluation' => $row->id]) }}" wire:navigate>{{ $row->title }}</a>
        @endif
        <span class="learning-agenda-meta">
            @if ($moment)
                <time datetime="{{ $row->all_day ? $moment->toDateString() : $moment->toIso8601String() }}">
                    {{ $moment->isToday() ? 'Hoy' : ($moment->isTomorrow() ? 'Mañana' : $moment->translatedFormat('D j M')) }}{{ $row->all_day ? '' : ' · '.$moment->format('H:i') }}
                </time>
            @else
                Sin fecha
            @endif
            @if ($task && $row->completed)
                · Completada
            @elseif ($task && $past)
                · Vencida
            @elseif (! $task && $past && $row->score === null)
                · Pendiente de nota
            @endif
        </span>
    </div>
    @if ($task && ! $row->completed)
        @if ($row->is_recurrent)
            <x-ui.action variant="text" icon="bi-arrow-repeat" :aria-label="'Gestionar tarea recurrente '.$row->title"
                x-on:click="$dispatch('task-editor', {action: 'openForm', id: '{{ $row->id }}', mode: 'edit'})" />
        @else
            <x-ui.action variant="text" icon="bi-check2" :aria-label="'Completar '.$row->title"
                wire:click="completeTask('{{ $row->id }}')" wire:loading.attr="disabled" />
        @endif
    @endif
</li>
