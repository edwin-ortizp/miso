@php
    $taskListRoute = collect(['tasks.list', 'tasks.index', 'tasks'])->first(fn ($name) => \Illuminate\Support\Facades\Route::has($name));
    $statisticsRoute = collect(['statistics', 'statistics.index'])->first(fn ($name) => \Illuminate\Support\Facades\Route::has($name));
    $eventIcons = ['appointment' => 'bi-hospital', 'checkup' => 'bi-clipboard2-pulse', 'procedure' => 'bi-bandaid', 'symptom' => 'bi-activity', 'illness' => 'bi-thermometer-half', 'vaccination' => 'bi-shield-plus'];
    $intensityOptions = array_combine(range(1, 10), range(1, 10));
    $days = fn (int $count) => $count.' '.($count === 1 ? 'día' : 'días');
@endphp

<x-module-shell module="health" class="health-page">
    <x-slot:actions>
        <x-module-actions :primary="['label' => 'Registrar evento', 'icon' => 'bi-plus-lg', 'event' => 'health-editor', 'detail' => ['action' => 'openForm']]"
                          :secondary="[['label' => 'Nuevo pendiente de salud', 'icon' => 'bi-list-check', 'event' => 'health-editor', 'detail' => ['action' => 'openTaskForm', 'id' => null], 'create' => true]]"
                          :split="true" />
    </x-slot:actions>

    <x-ui.management-card id="health-timeline" title="Cronología de salud" icon="bi-clock-history" class="health-timeline-section"
                          :count="'('.$events->total().' / '.$totalCount.')'" search="search" search-placeholder="Buscar eventos"
                          :active-filters="count($activeFilters)" :paginator="$events" noun="eventos"
                          alpine="draftRange: 'all', draftStatus: 'all', draftTypes: []"
                          on-filters-open="draftRange = $wire.range; draftStatus = $wire.status; draftTypes = [...$wire.types]">
        <x-slot:filters>
            <x-ui.select name="range" label="Rango de tiempo" :options="$ranges" :selected="$range" icon="bi-calendar-range" x-model="draftRange" />
            <x-ui.select name="status" label="Estado" :options="$statuses" :selected="$status" icon="bi-activity" x-model="draftStatus" />
            <x-ui.multi-select name="types" model-expression="draftTypes" :live="false" label="Tipo" :options="$typeLabels" all-label="Todos los tipos" icon="bi-tag" />
        </x-slot:filters>
        <x-slot:filterActions>
            <x-ui.action variant="outlined" icon="bi-eraser" wire:click="clearFilters" x-on:click="filtersOpen = false">Limpiar</x-ui.action>
            <x-ui.action variant="filled" icon="bi-funnel-fill" x-on:click="$wire.applyFilters(draftRange, draftStatus, draftTypes); filtersOpen = false">Filtrar</x-ui.action>
        </x-slot:filterActions>
        @if ($statisticsRoute)
            <x-slot:menu>
                <x-ui.menu-item icon="bi-bar-chart-line" :href="route($statisticsRoute)">Ver estadísticas</x-ui.menu-item>
            </x-slot:menu>
        @endif

        @if (count($activeFilters) > 0)
            <x-slot:strip>
                <x-ui.applied-filters :filters="$activeFilters" />
            </x-slot:strip>
        @endif

        <div class="health-timeline">
            @forelse ($events as $event)
                @php
                    $task = $event->tasks->sortBy('created_at')->first();
                    // Un procedimiento agendado se sigue como cita hasta que llega su fecha.
                    $tracks = in_array($event->type, \App\Models\HealthEvent::EVOLUTION_TYPES, true) && ! $event->event_date->isFuture();
                    $logs = $tracks ? $event->logs : collect();
                    $details = $event->details ?? [];
                    $areasLabel = \App\Models\HealthEvent::bodyAreasLabel($event->bodyAreas(), $details['body_area_note'] ?? null);
                    $detailLabel = collect([
                        match (true) {
                            isset($details['condition']) => \App\Models\HealthEvent::illnessLabel($details['condition'], $details['condition_note'] ?? null),
                            isset($details['provider']) => $details['provider'],
                            isset($details['vaccine_name']) => $details['vaccine_name'],
                            default => null,
                        },
                        $areasLabel,
                    ])->filter()->implode(' · ') ?: $typeLabels[$event->type];
                    $hasCareDetails = collect(['provider', 'specialty', 'facility', 'vaccine_name'])->contains(fn ($key) => isset($details[$key]));
                    if ($tracks && $event->end_date) {
                        $span = $event->event_date->translatedFormat('j M').'–'.$event->end_date->translatedFormat('j M').' · '.$days((int) $event->event_date->diffInDays($event->end_date) + 1);
                    } elseif ($tracks) {
                        $span = 'desde '.$event->event_date->translatedFormat('j M').' · '.$days((int) $event->event_date->diffInDays(today()) + 1);
                    } else {
                        $span = $event->event_date->translatedFormat('j M Y');
                    }
                @endphp
                <article class="health-event {{ $event->event_date->isFuture() ? 'is-upcoming' : '' }}" wire:key="health-event-{{ $event->id }}" x-data="{ expanded: false }">
                    <div class="health-event-date" aria-hidden="true">
                        <strong>{{ $event->event_date->translatedFormat('d') }}</strong>
                        <span>{{ \Illuminate\Support\Str::upper(rtrim($event->event_date->translatedFormat('M'), '.')) }}</span>
                        <small>{{ $event->event_date->format('Y') }}</small>
                    </div>

                    <div class="health-event-card" x-bind:class="expanded && 'is-open'">
                        <div class="health-event-summary" role="button" tabindex="0"
                             x-on:click="expanded = !expanded" x-on:keydown.enter.prevent="expanded = !expanded" x-on:keydown.space.prevent="expanded = !expanded"
                             x-bind:aria-expanded="expanded.toString()" aria-controls="health-event-panel-{{ $event->id }}">
                            <span class="health-event-icon"><i class="bi {{ $eventIcons[$event->type] ?? 'bi-heart-pulse' }}" aria-hidden="true"></i></span>
                            <div class="health-event-heading">
                                <strong class="health-summary-title">{{ $event->title }}</strong>
                                <span class="health-event-meta">{{ $detailLabel }} · {{ $span }}</span>
                            </div>
                            <div class="health-event-status">
                                @if ($tracks)
                                    <span class="health-status-chip {{ $event->end_date ? 'health-status-chip--recovered' : 'health-status-chip--active' }}">{{ $event->end_date ? 'Recuperado' : 'En seguimiento' }}</span>
                                    @if (! $event->end_date && $logs->isNotEmpty())
                                        <small>Intensidad {{ $logs->last()->intensity }}/10</small>
                                    @endif
                                @elseif ($event->event_date->isFuture())
                                    <span class="health-status-chip health-status-chip--upcoming">Próximo</span>
                                @else
                                    <span class="health-status-chip">{{ $typeLabels[$event->type] }}</span>
                                @endif
                            </div>
                            <i class="bi bi-chevron-down health-summary-chevron" x-bind:class="expanded && 'is-open'" aria-hidden="true"></i>
                        </div>

                        <div class="health-event-panel" id="health-event-panel-{{ $event->id }}" x-show="expanded" x-cloak x-transition.opacity.duration.200ms>
                            @if ($event->end_date && ! $tracks)
                                <p class="health-muted">Hasta {{ $event->end_date->translatedFormat('j \d\e F \d\e Y') }}</p>
                            @endif

                            @if ($hasCareDetails)
                                <div class="health-details">
                                    @if (isset($details['provider']))<span><i class="bi bi-person-badge" aria-hidden="true"></i> {{ $details['provider'] }}</span>@endif
                                    @if (isset($details['specialty']))<span><i class="bi bi-heart-pulse" aria-hidden="true"></i> {{ $details['specialty'] }}</span>@endif
                                    @if (isset($details['facility']))<span><i class="bi bi-building" aria-hidden="true"></i> {{ $details['facility'] }}</span>@endif
                                    @if (isset($details['vaccine_name']))<span><i class="bi bi-shield-check" aria-hidden="true"></i> {{ $details['vaccine_name'] }}{{ isset($details['dose']) ? ' · '.$details['dose'] : '' }}</span>@endif
                                </div>
                            @endif

                            @if ($tracks)
                                <div class="health-evolution">
                                    <div class="health-evolution__chart-card">
                                        <p class="health-section-label">Evolución de intensidad diaria</p>
                                        @if ($logs->isNotEmpty())
                                            <template x-if="expanded">
                                                @php $chartVersion = $logs->map(fn ($log) => $log->updated_at->timestamp)->max(); @endphp
                                                <div wire:ignore wire:key="health-chart-{{ $event->id }}-{{ $chartVersion }}" class="health-evolution__chart"
                                                     data-health-chart='@json($logs->map(fn ($log) => ['date' => $log->date->translatedFormat('j'), 'intensity' => $log->intensity])->values())'
                                                     role="img" aria-label="Evolución de intensidad diaria de {{ $event->title }}"></div>
                                            </template>
                                        @elseif (isset($details['severity']))
                                            <p class="health-muted"><i class="bi bi-info-circle" aria-hidden="true"></i> Intensidad inicial: {{ $details['severity'] }}/10.</p>
                                        @else
                                            <p class="health-muted">Aún no hay días registrados.</p>
                                        @endif
                                    </div>

                                    <div class="health-followup">
                                        <p class="health-section-label">Seguimiento</p>
                                        @if ($logs->isNotEmpty())
                                            <ol class="health-log-timeline">
                                                @foreach ($logs->sortByDesc('date') as $log)
                                                    <li class="health-log-timeline__item" wire:key="health-log-{{ $log->id }}">
                                                        <span class="health-log-timeline__dot" aria-hidden="true"></span>
                                                        <div class="health-log-timeline__body">
                                                            <strong><time datetime="{{ $log->date->toDateString() }}">{{ $log->date->translatedFormat('j M') }}</time> · {{ $log->intensity }}/10</strong>
                                                            @if ($log->notes)<span>{{ $log->notes }}</span>@endif
                                                        </div>
                                                        <x-ui.menu size="sm" :label="'Opciones del '.$log->date->translatedFormat('j M')">
                                                            <x-ui.menu-item icon="bi-pencil" x-on:click="$dispatch('health-editor', {action: 'editLog', id: '{{ $log->id }}'})">Editar día</x-ui.menu-item>
                                                            <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteLog('{{ $log->id }}')" wire:confirm="¿Eliminar este registro diario?">Eliminar día</x-ui.menu-item>
                                                        </x-ui.menu>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        @else
                                            <p class="health-muted">Registra cómo te sientes cada día para ver la evolución.</p>
                                        @endif
                                        @if ($event->notes)<p class="health-notes">{{ $event->notes }}</p>@endif
                                    </div>
                                </div>
                            @else
                                @if ($event->notes)<p class="health-notes">{{ $event->notes }}</p>@endif
                                @if ($task)
                                    <a href="{{ route('tasks.planning') }}" class="health-task-link">
                                        <i class="bi {{ $task->completed ? 'bi-check-circle-fill' : 'bi-calendar-check' }}" aria-hidden="true"></i>
                                        <span>{{ $task->completed ? 'Acción completada' : 'Ver en Planificación' }}</span><i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </a>
                                @elseif ($event->event_date->isFuture())
                                    <p class="health-muted"><i class="bi bi-info-circle" aria-hidden="true"></i> Este registro no requiere una acción de agenda.</p>
                                @endif
                            @endif

                            <div class="health-event-actions">
                                @if ($tracks && ! $event->end_date)
                                    <x-ui.action variant="tonal" icon="bi-plus-lg" x-on:click="$dispatch('health-editor', {action: 'openLogForm', id: '{{ $event->id }}'})">{{ $event->type === 'procedure' ? 'Registrar día de recuperación' : 'Registrar día' }}</x-ui.action>
                                    <x-ui.action variant="text" icon="bi-check2-circle" x-on:click="$dispatch('health-editor', {action: 'openRecoveryForm', id: '{{ $event->id }}'})">Marcar recuperación</x-ui.action>
                                @elseif ($tracks)
                                    <x-ui.action variant="text" icon="bi-arrow-counterclockwise" wire:click="reopenEvolution('{{ $event->id }}')">Aún continúa</x-ui.action>
                                @endif
                                <x-ui.menu :label="'Más opciones de '.$event->title" class="health-event-actions__more">
                                    <x-ui.menu-item icon="bi-pencil" x-on:click="$dispatch('health-editor', {action: 'openForm', id: '{{ $event->id }}'})">Editar evento</x-ui.menu-item>
                                    @if ($task)
                                        <x-ui.menu-item icon="bi-calendar-check" :href="route('tasks.planning')">Ver en planificación</x-ui.menu-item>
                                    @endif
                                    <x-ui.menu-divider />
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteEvent('{{ $event->id }}')" wire:confirm="¿Eliminar este evento? La tarea asociada se conservará.">Eliminar</x-ui.menu-item>
                                </x-ui.menu>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="health-empty">
                    @if ($totalCount === 0)
                        <i class="bi bi-heart-pulse" aria-hidden="true"></i>
                        <h3 class="md-title-large">Aún no hay registros</h3>
                        <p>Registra una cita, un síntoma, una vacuna o un próximo control.</p>
                    @else
                        <i class="bi bi-funnel" aria-hidden="true"></i>
                        <h3 class="md-title-large">Ningún evento coincide con los filtros</h3>
                        <p>Ajusta el rango, el estado o el tipo para ver más registros.</p>
                        <x-ui.action variant="tonal" wire:click="clearFilters">Limpiar filtros</x-ui.action>
                    @endif
                </div>
            @endforelse
        </div>
    </x-ui.management-card>

    <x-slot:rail>
        <section class="health-card" aria-labelledby="health-moments-title">
            <header class="health-card__head">
                <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                <h2 id="health-moments-title">Momentos de enfermedad</h2>
                @if ($statisticsRoute)
                    <x-ui.icon-action icon="bi-chevron-right" label="Ver estadísticas" size="sm" :href="route($statisticsRoute)" />
                @endif
            </header>
            <div class="health-card__body">
                <x-ui.select name="illnessPeriod" label="Periodo" :options="$illnessPeriods" :selected="$illnessPeriod" icon="bi-calendar3" wire:model.live="illnessPeriod" />
                <div wire:ignore wire:key="health-moments-{{ $illnessPeriod }}-{{ md5(json_encode($moments['months'])) }}"
                     class="health-moments__chart"
                     data-health-months-chart='@json($moments['months'])'
                     role="img" aria-label="Eventos de salud por mes · {{ $moments['label'] }}"></div>
                <dl class="health-moments__kpis">
                    <div><dt>Meses con eventos</dt><dd>{{ $moments['monthsWithEvents'] }}</dd></div>
                    <div><dt>Total de eventos</dt><dd>{{ $moments['total'] }}</dd></div>
                    <div><dt>Promedio por mes</dt><dd>{{ $moments['average'] }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="health-card" aria-labelledby="health-next-title">
            <header class="health-card__head">
                <i class="bi bi-calendar2-heart" aria-hidden="true"></i>
                <h2 id="health-next-title">Próximo cuidado</h2>
            </header>
            <div class="health-card__body">
                @if ($nextEvent)
                    @php $nextDay = $nextEvent->event_date->isToday() ? 'Hoy' : ($nextEvent->event_date->isTomorrow() ? 'Mañana' : \Illuminate\Support\Str::ucfirst($nextEvent->event_date->translatedFormat('l'))); @endphp
                    <button type="button" class="md-list-item-link health-next" x-on:click="$dispatch('health-editor', {action: 'openForm', id: '{{ $nextEvent->id }}'})">
                        <span class="health-event-icon"><i class="bi {{ $eventIcons[$nextEvent->type] ?? 'bi-heart-pulse' }}" aria-hidden="true"></i></span>
                        <span class="health-next__body">
                            <strong>{{ $nextEvent->title }}</strong>
                            <span>{{ $nextDay }} · {{ $nextEvent->event_date->translatedFormat('j M') }} · {{ $typeLabels[$nextEvent->type] }}</span>
                        </span>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                @else
                    <p class="health-muted">No hay eventos futuros registrados.</p>
                @endif
            </div>
        </section>

        <section class="health-card" aria-labelledby="health-pending-title">
            <header class="health-card__head">
                <i class="bi bi-list-check" aria-hidden="true"></i>
                <h2 id="health-pending-title">Pendientes de salud</h2>
                <span class="health-card__count" aria-label="{{ $pendingHealthTasks }} pendientes">{{ $pendingHealthTasks }}</span>
            </header>
            <div class="health-card__body">
                @if ($healthTasks->isEmpty())
                    <p class="health-muted">No tienes pendientes de salud.</p>
                @else
                    <ul class="health-pending-list">
                        @foreach ($healthTasks as $pending)
                            @php
                                $pendingDate = $pending->start_date ?? $pending->end_date;
                                $pendingMeta = $pending->completed
                                    ? 'Completada '.$pending->completed_at?->translatedFormat('j M')
                                    : ($pendingDate ? ($pendingDate->isToday() ? 'Para hoy' : $pendingDate->translatedFormat('D j M')) : 'Sin fecha');
                            @endphp
                            <li class="health-pending {{ $pending->completed ? 'is-done' : '' }}" wire:key="health-pending-{{ $pending->id }}">
                                <i class="bi {{ $pending->completed ? 'bi-check-circle-fill' : 'bi-circle' }} health-pending__status" aria-hidden="true"></i>
                                <div class="health-pending__body">
                                    <x-ui.task-code :code="$pending->code" /> <strong>{{ $pending->title }}</strong>
                                    <span>{{ $pendingMeta }}</span>
                                </div>
                                <x-ui.menu :label="'Más opciones de '.$pending->title">
                                    @if ($taskListRoute)
                                        <x-ui.menu-item icon="bi-pencil" :href="route($taskListRoute, ['editTask' => $pending->id])">Editar</x-ui.menu-item>
                                    @endif
                                    @if ($pending->completed)
                                        <x-ui.menu-item icon="bi-arrow-counterclockwise" wire:click="reopenTask('{{ $pending->id }}')">Marcar como pendiente</x-ui.menu-item>
                                    @else
                                        <x-ui.menu-item icon="bi-check2-circle" wire:click="completeTask('{{ $pending->id }}')">Marcar como completado</x-ui.menu-item>
                                        <x-ui.menu-item icon="bi-calendar-event" x-on:click="$dispatch('health-editor', {action: 'openRescheduleTask', id: '{{ $pending->id }}'})">Reprogramar</x-ui.menu-item>
                                    @endif
                                    <x-ui.menu-divider />
                                    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="deleteTask('{{ $pending->id }}')" wire:confirm="¿Eliminar este pendiente de salud?">Eliminar</x-ui.menu-item>
                                </x-ui.menu>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div>
                </div>
            </div>
        </section>
    </x-slot:rail>

    <livewire:health.health-editor :initial-open="$showForm" :initial-areas="$bodyAreas" />
</x-module-shell>
