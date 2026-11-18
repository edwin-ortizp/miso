@php
    use App\Services\Learning\GradeProjection;
    $primary = $hasSubjects
        ? ['label' => 'Agregar tarea', 'icon' => 'bi-plus-lg', 'event' => 'study-dashboard-task', 'create' => true]
        : ['label' => 'Agregar formación', 'icon' => 'bi-plus-lg', 'event' => 'study-dashboard-formation', 'create' => true];
@endphp
<x-module-shell module="learning" title="Mi estudio" archetype="list" active-route="learning.study" class="learning-page"
    x-on:study-dashboard-task.window="$event.target.closest('button')?.focus({preventScroll: true}); $dispatch('study-academic-task', {formation: $wire.formationFilter, semester: $wire.semesterFilter})"
    x-on:study-focus-create.window="requestAnimationFrame(() => requestAnimationFrame(() =>
        document.querySelector('.md-fab-host[data-module=learning] :is(.md-module-primary-fab, .lt-chat__launcher)')?.focus({preventScroll: true})))">
    <x-slot:actions>
        <div wire:ignore>
            <x-module-actions :primary="$primary" />
        </div>
    </x-slot:actions>
    @if ($hasSubjects)
        <div class="learning-study-filters">
            <x-ui.select name="formationFilter" label="Formación" wire:model.live="formationFilter"
                :options="['' => 'Todas las formaciones'] + $formations" />
            <x-ui.select name="semesterFilter" label="Semestre" wire:model.live="semesterFilter"
                :options="['' => 'Todos los semestres', 'none' => 'Sin semestre'] + $semesters" />
        </div>
    @endif
    <div class="learning-academic-feedback" role="status" aria-live="polite">{{ $message }}</div>
    <div class="learning-study-grid {{ $window === 'week' ? '' : 'learning-study-grid--wide' }}">
        <x-ui.card title="Agenda" icon="bi-calendar2-week" as="section">
            @if ($window !== 'week')
                <div class="learning-academic-tabs" aria-label="Fechas de la agenda">
                    <x-ui.action :variant="$window === 'all' ? 'tonal' : 'text'" :aria-pressed="$window === 'all' ? 'true' : 'false'" wire:click="setWindow('all')">Con fecha</x-ui.action>
                    <x-ui.action :variant="$window === 'undated' ? 'tonal' : 'text'" :aria-pressed="$window === 'undated' ? 'true' : 'false'" wire:click="setWindow('undated')">Sin fecha</x-ui.action>
                </div>
                @if ($window === 'all')
                    <div class="learning-academic-fields learning-study-dates">
                        <x-ui.field name="from" label="Desde" type="date" wire:model.live="from" />
                        <x-ui.field name="through" label="Hasta" type="date" wire:model.live="through" />
                    </div>
                    @if ($from !== '' && $through !== '' && $from > $through)
                        <p role="alert">La fecha final debe ser igual o posterior a la inicial.</p>
                    @endif
                @endif
            @endif
            <p class="learning-academic-context" wire:loading wire:target="formationFilter,semesterFilter,setWindow,from,through" role="status">Actualizando agenda…</p>
            @if ($entries->isNotEmpty())
                <ul class="learning-agenda-list">
                    @foreach ($entries as $row)
                        @include('livewire.learning.agenda-row', ['showSubject' => true])
                    @endforeach
                </ul>
                {{ $entries->links() }}
            @elseif (! $hasSubjects)
                <x-ui.state variant="empty" icon="bi-mortarboard" title="Tu agenda empieza con una materia"
                    message="Organiza tus materias en Formaciones para agregar entregas y parciales.">
                    <x-slot:actions><x-ui.action variant="text" :href="route('learning')" wire:navigate>Ver formaciones</x-ui.action></x-slot:actions>
                </x-ui.state>
            @else
                <x-ui.state :variant="$formationFilter || $semesterFilter || $from || $through ? 'filtered-empty' : 'empty'" icon="bi-calendar2-check"
                    :title="$window === 'week' ? 'Sin compromisos para estos días' : 'Sin compromisos en esta vista'"
                    :message="$window === 'week' ? 'Aquí verás lo vencido y lo próximo de los siguientes siete días.' : null" />
            @endif
            @if ($hasSubjects)
                <x-slot:actions>
                    <x-ui.action variant="text" wire:click="setWindow('{{ $window === 'week' ? 'all' : 'week' }}')">
                        {{ $window === 'week' ? 'Ver toda la agenda' : 'Volver a próximos siete días' }}
                    </x-ui.action>
                </x-slot:actions>
            @endif
        </x-ui.card>

        @if ($hasSubjects && $window === 'week')
            <x-ui.card title="Materias que requieren atención" icon="bi-graph-up" as="section">
                @if ($attention)
                    <ul class="learning-attention-list">
                        @foreach ($attention as $item)
                            <li wire:key="attention-{{ $item['subject']->id }}">
                                <a class="learning-agenda-link" href="{{ route('learning.units.show', [$item['subject']->formation_id, $item['subject']->id, 'section' => 'grades']) }}" wire:navigate>{{ $item['subject']->title }}</a>
                                <span class="learning-agenda-meta">{{ $item['subject']->formation->title }}</span>
                                <p>{{ GradeProjection::headline($item['result']) }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="learning-academic-context">Aquí aparecerán las materias cuyo cálculo indique que necesitas una nota alta para alcanzar el mínimo.</p>
                @endif
            </x-ui.card>
        @endif
    </div>
    @if ($hasSubjects)
        <livewire:learning.academic-editor key="study-dashboard-academic-editor" />
        <livewire:task.task-editor key="study-dashboard-task-editor" />
    @else
        <livewire:learning.study-formation-editor key="study-dashboard-formation-editor" />
    @endif
</x-module-shell>
