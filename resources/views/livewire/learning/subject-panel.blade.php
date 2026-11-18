@php
    use App\Services\Learning\GradeProjection;
    $cutMode = $book->mode === 'cuts';
    $groups = $cutMode ? $cuts->all() + ['unassigned' => null] : ['direct' => null];
    $ready = $projection['state'] !== 'setup';
    $hasPending = $evaluations->contains(fn ($entry) => $entry->score === null);
@endphp
<div class="learning-subject-panels">
    <div class="learning-academic-feedback" role="status" aria-live="polite">{{ $message }}</div>
    @error('deletion') <p role="alert">{{ $message }}</p> @enderror

    @if ($section === 'summary' || $section === 'grades')
        <x-ui.card :title="$section === 'summary' ? 'Estado en la materia' : 'Calificación'" icon="bi-graph-up" as="section">
            @if ($evaluations->isNotEmpty() || $cuts->isNotEmpty())
                <p class="learning-academic-forecast">{{ GradeProjection::headline($projection) }}</p>
                <dl class="learning-grade-metrics">
                    <div>
                        <dt>Promedio de lo calificado</dt>
                        <dd>{{ $projection['average'] === null ? 'Sin notas ponderadas' : GradeProjection::number($projection['average']) }}</dd>
                    </div>
                    <div>
                        <dt>Peso ya calificado</dt>
                        <dd>{{ GradeProjection::percent($projection['evaluated']) }}</dd>
                    </div>
                    <div>
                        <dt>Mínimo para pasar</dt>
                        <dd>{{ GradeProjection::number($book->passing) }}</dd>
                    </div>
                </dl>
                @if (! $ready)
                    @if ($projection['setup'] < 10_000)
                        <p class="md-supporting-text">Falta definir {{ GradeProjection::percent((10_000 - $projection['setup']) / 100) }} del peso de la materia{{ $cutMode ? ' entre sus cortes' : '' }}.</p>
                    @endif
                    @if ($projection['unassigned'])
                        <p class="md-supporting-text">Hay evaluaciones pendientes de {{ $cutMode ? 'corte o peso' : 'peso' }}.</p>
                    @endif
                    @if (array_filter($projection['insideCuts'], fn ($weight) => $weight < 10_000))
                        <p class="md-supporting-text">Completa los porcentajes de las evaluaciones dentro de cada corte.</p>
                    @endif
                @endif
                @if ($projection['rounding'])
                    <p class="md-supporting-text">El resultado antes de redondear queda por debajo del mínimo.</p>
                @endif
            @else
                <x-ui.state variant="empty" icon="bi-journal-check" title="Aún no hay evaluaciones"
                    message="Agrega tu primer parcial o indica que una tarea cuenta para la nota." />
            @endif

            <x-slot:actions>
                @if ($section === 'summary')
                    <x-ui.action variant="text" :href="route('learning.units.show', [$subject->formation_id, $subject->id, 'section' => 'grades'])" wire:navigate>Ver notas</x-ui.action>
                @else
                    @if ($ready && $hasPending)
                        <x-ui.action variant="tonal" icon="bi-calculator" x-on:click="$dispatch('study-academic-edit', {type: 'simulate'})">Simular notas</x-ui.action>
                    @endif
                    <x-ui.action variant="text" icon="bi-sliders" x-on:click="$dispatch('study-academic-edit', {type: 'book'})">Configurar calificación</x-ui.action>
                @endif
            </x-slot:actions>
        </x-ui.card>
    @endif

    @if ($section === 'summary' || $section === 'tasks')
        <x-ui.card :title="$section === 'summary' ? 'Próximo compromiso' : 'Tareas de la materia'" icon="bi-list-task" as="section">
            @if ($entries->isEmpty())
                <x-ui.state variant="empty" icon="bi-calendar2-check"
                    :title="$section === 'summary' ? 'Sin compromisos con fecha' : 'Aún no hay tareas en esta materia'" />
            @else
                <ul class="learning-agenda-list">
                    @foreach ($entries as $row)
                        @include('livewire.learning.agenda-row')
                    @endforeach
                </ul>
                @if ($section === 'tasks')
                    {{ $entries->links() }}
                @endif
            @endif
            @if ($section === 'summary')
                <x-slot:actions>
                    <x-ui.action variant="text" :href="route('learning.units.show', [$subject->formation_id, $subject->id, 'section' => 'tasks'])" wire:navigate>Ver tareas</x-ui.action>
                </x-slot:actions>
            @endif
        </x-ui.card>
    @endif

    @if ($section === 'grades' && ($evaluations->isNotEmpty() || $cuts->isNotEmpty()))
        <x-ui.card title="Evaluaciones" icon="bi-journal-text" as="section">
            @foreach ($groups as $groupId => $cut)
                @php
                    $group = $cut ? $evaluations->where('cut_id', $groupId) : $evaluations->whereNull('cut_id');
                    $assigned = $cut ? ($projection['insideCuts'][$cut->id] ?? 0) : null;
                @endphp
                @if ($cut || $group->isNotEmpty())
                    <section class="learning-grade-group" wire:key="grade-group-{{ $groupId }}">
                        @if ($cut)
                            <div class="learning-grade-group-head">
                                <div>
                                    <h4>{{ $cut->title }} · {{ GradeProjection::percent($cut->weight / 100) }} de la materia</h4>
                                    @if ($assigned < 10_000)
                                        <p class="md-supporting-text">Por asignar dentro del corte {{ GradeProjection::percent((10_000 - $assigned) / 100) }}</p>
                                    @endif
                                </div>
                                <x-ui.menu :label="'Acciones de '.$cut->title">
                                    <x-ui.menu-item icon="bi-pencil" x-on:click="$dispatch('study-academic-edit', {type: 'cut', id: '{{ $cut->id }}'})">Editar corte</x-ui.menu-item>
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteAcademic('cut', '{{ $cut->id }}')"
                                        wire:confirm="¿Eliminar este corte?">Eliminar corte</x-ui.menu-item>
                                </x-ui.menu>
                            </div>
                        @elseif ($cutMode)
                            <h4>Sin corte asignado</h4>
                        @endif

                        @if ($group->isEmpty())
                            <p class="md-supporting-text">Aún no hay evaluaciones en este corte.</p>
                        @else
                            <ul class="learning-agenda-list">
                                @foreach ($group as $entry)
                                    @php
                                        $target = $evaluation === $entry->id;
                                        $linked = filled($entry->task_id);
                                        $date = $linked ? $entry->task_deadline : $entry->scheduled_on;
                                        $dateLabel = $date ? \Carbon\Carbon::parse($date)->locale('es')->translatedFormat('j M') : 'Sin fecha';
                                        $time = $linked ? ($date && ! $entry->task_all_day ? substr($date, 11, 5) : '') : substr($entry->scheduled_at ?? '', 0, 5);
                                    @endphp
                                    <li wire:key="evaluation-{{ $entry->id }}" class="learning-grade-row {{ $target ? 'learning-grade-row--selected' : '' }}"
                                        @if ($target) x-data x-init="$nextTick(() => $el.querySelector('[tabindex]').focus())" @endif>
                                        <div class="learning-agenda-description">
                                            <span class="learning-agenda-meta">
                                                {{ \App\Services\Learning\StudyAgenda::type($entry->kind) }} · {{ $dateLabel }}{{ $time ? ' · '.$time : '' }}
                                                @if ($linked && $entry->task_completed) · Entregada @endif
                                            </span>
                                            <strong tabindex="-1">{{ $entry->task_title ?? $entry->title }}</strong>
                                            <span class="learning-agenda-meta">
                                                {{ $entry->weight === null ? 'Peso pendiente' : GradeProjection::percent($entry->weight / 100).($cutMode ? ' del corte' : ' de la materia') }}
                                            </span>
                                        </div>
                                        <div class="learning-grade-row-actions">
                                            <x-ui.action :variant="$entry->score === null ? 'tonal' : 'text'"
                                                :aria-label="($entry->score === null ? 'Registrar nota de ' : 'Editar nota de ').($entry->task_title ?? $entry->title)"
                                                x-on:click="$dispatch('study-academic-edit', {type: 'score', id: '{{ $entry->id }}'})">
                                                {{ $entry->score === null ? 'Registrar nota' : GradeProjection::number($entry->score) }}
                                            </x-ui.action>
                                            <x-ui.menu :label="'Acciones de '.($entry->task_title ?? $entry->title)">
                                                @if ($linked)
                                                    <x-ui.menu-item icon="bi-list-task" x-on:click="$dispatch('task-editor', {action: 'openForm', id: '{{ $entry->task_id }}'})">Abrir tarea</x-ui.menu-item>
                                                @endif
                                                <x-ui.menu-item icon="bi-pencil" x-on:click="$dispatch('study-academic-edit', {type: 'evaluation', id: '{{ $entry->id }}'})">Editar evaluación</x-ui.menu-item>
                                                <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteAcademic('evaluation', '{{ $entry->id }}')"
                                                    :wire:confirm="$linked ? '¿Eliminar la evaluación y su nota? La tarea se conserva.' : '¿Eliminar la evaluación y su nota?'">Eliminar evaluación</x-ui.menu-item>
                                            </x-ui.menu>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif
            @endforeach
        </x-ui.card>
    @endif
</div>
