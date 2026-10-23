<div class="wall-panel" data-module="home" x-data="wallPanel({{ \Illuminate\Support\Js::from(config('app.timezone')) }})" @panel-goals.window="goalsOpen = true">
    <main class="wall-grid" id="panel-main">
        <h1 class="visually-hidden">Panel de pared</h1>
        <section class="wall-now" aria-label="Ahora">
            <div class="wall-clock"><strong x-text="clock"></strong><span x-text="dateLabel"></span><span>{{ \App\Livewire\Habit\HabitSettings::TIMES_OF_DAY[$block] }}</span>
                <div class="wall-view-controls" role="group" aria-label="Vista del panel">
                    <x-ui.icon-action icon="bi-arrow-clockwise" label="Actualizar panel" @click="refreshData()" x-bind:disabled="refreshState?.refreshing" />
                    <x-ui.icon-action icon="bi-moon" label="Modo noche" @click="toggleNight()" x-bind:aria-pressed="night" />
                    <x-ui.icon-action icon="bi-clock" label="Modo ambiente" @click="ambient = true" />
                    <span class="wall-update-status" role="status" aria-live="polite">
                        <span x-show="refreshState?.refreshing" x-cloak>Actualizando…</span>
                        <span x-show="!refreshState?.refreshing">Última actualización: <time x-text="lastUpdateLabel" :datetime="$wire.lastUpdatedAt"></time></span>
                        <span class="d-block" x-show="refreshState?.refreshError" x-text="refreshState?.refreshError" x-cloak></span>
                    </span>
                </div>
            </div>
            @php
                $dayTasks = collect($week[0]['rows'])->where('kind', 'task');
                $doneTasks = $dayTasks->where('done', true)->count();
                $rings = [
                    ['Hábitos', count($completions).'/'.$habits->count(), $habits->count() ? count($completions)/$habits->count()*100 : 0, 'habitsOpen = true'],
                    ['Agua', round($waterGoal ? $waterTotal/$waterGoal*100 : 0).'%', $waterGoal ? $waterTotal/$waterGoal*100 : 0, 'waterOpen = true'],
                    ['Tareas', $doneTasks.'/'.$dayTasks->count(), $dayTasks->count() ? $doneTasks/$dayTasks->count()*100 : 0, 'dayIndex = 0; agendaOpen = true'],
                    ['Min. foco', $focusMinutes.'/'.$focusGoal, $focusGoal ? $focusMinutes/$focusGoal*100 : 0, '$dispatch("panel-focus", {})'],
                ];
            @endphp
            <div class="wall-rings">
                @foreach ($rings as [$label, $value, $percent, $action])
                    <button type="button" class="md-btn-text wall-ring-button" @click="{{ $action }}" aria-haspopup="dialog"><span class="wall-ring wall-ring--{{ ['habits', 'water', 'tasks', 'focus'][$loop->index] }}" style="--md-progress-value: {{ min(100, max(0, $percent)) }}"><b>{{ $value }}</b></span><span>{{ $label }}</span></button>
                @endforeach
            </div>
            <div class="wall-timeline" wire:key="timeline-{{ md5(json_encode($timeline)) }}" x-data="wallTimeline({{ \Illuminate\Support\Js::from($timeline) }}, {{ \Illuminate\Support\Js::from(config('app.timezone')) }})" aria-label="Línea de tiempo de hoy">
                <x-ui.icon-action icon="bi-chevron-left" label="Horas anteriores" @click="shift(-240)" />
                <div class="wall-timeline__body">
                    <div class="wall-timeline__track">
                        <span class="wall-timeline__now" x-show="nowMinute >= start && nowMinute <= start + 480" x-effect="$el.style.setProperty('--md-progress-value', (nowMinute - start) / 480 * 100)">Ahora</span>
                        <template x-for="(group, index) in groups" :key="index"><x-ui.action variant="tonal" class="wall-pin wall-control" x-bind:class="{ 'is-done': group.every(item => item.done) }" x-effect="$el.style.setProperty('--md-progress-value', (group[0].minute - start) / 480 * 100)" x-bind:aria-label="group.map(item => item.time + ' ' + item.title).join(', ')" @click="cluster = group; clusterOpen = true"><span x-text="group.length > 1 ? group.length : group[0].time"></span></x-ui.action></template>
                    </div>
                    <div class="wall-timeline__scale"><template x-for="offset in [0, 120, 240, 360, 480]"><span x-text="formatMinute(start + offset)"></span></template></div>
                </div>
                <x-ui.icon-action icon="bi-chevron-right" label="Horas siguientes" @click="shift(240)" />
                <x-ui.dialog standard state="clusterOpen" title="En este momento" size="lg">
                    <template x-for="item in cluster"><p><b x-text="item.time"></b> <span x-text="item.title"></span> <span x-text="item.done ? ' · Hecho' : ''"></span></p></template>
                    <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="clusterOpen = false">Cerrar</x-ui.action></x-slot:actions>
                </x-ui.dialog>
            </div>
        </section>
        <section class="wall-next" aria-label="Lo próximo">
            <h2>Lo próximo</h2>
            @if ($next = $nextTasks->first())
                <strong>{{ $next['title'] }}</strong><p>Hoy · {{ $next['time'] }}</p>
                <div class="d-flex gap-2 flex-wrap">
                    <x-ui.action class="wall-control" variant="filled" icon="bi-check2-circle" wire:click="toggleTask('{{ $next['id'] }}')" wire:loading.attr="disabled">Completar</x-ui.action>
                    <x-ui.action class="wall-control" variant="tonal" @click="$dispatch('panel-quick', {kind: 'snooze', id: {{ \Illuminate\Support\Js::from($next['id']) }}})">Posponer</x-ui.action>
                    <x-ui.action class="wall-control" variant="tonal" icon="bi-stopwatch" @click="$dispatch('panel-focus', {start: true, description: {{ \Illuminate\Support\Js::from($next['title']) }}})">Foco</x-ui.action>
                </div>
                @if ($after = $nextTasks->get(1))<p class="wall-muted">Después: {{ $after['title'] }} · {{ $after['time'] }}</p>@endif
            @else
                <strong>Sin tareas próximas con hora</strong><p>Consulta la agenda para ver las tareas sin hora.</p>
            @endif
        </section>
        <section class="wall-week" aria-labelledby="panel-week-title">
            <header class="wall-section-head"><h2 id="panel-week-title">Próximos días</h2><span>{{ $week[0]['date']->locale('es')->translatedFormat('d M') }} – {{ $week[4]['date']->locale('es')->translatedFormat('d M') }}</span><div class="md-chip-rail wall-layer-rail" role="group" aria-label="Capas del calendario">
                @foreach (['task' => 'Tareas', 'plan' => 'Planes', 'person' => 'Personas', 'meal' => 'Comidas'] as $key => $label)
                    <x-ui.chip variant="filter" x-bind:class="{selected: layers.{{ $key }}}" x-bind:aria-pressed="layers.{{ $key }}" @click="layers.{{ $key }} = !layers.{{ $key }}">{{ $label }}</x-ui.chip>
                @endforeach
            </div></header>
            <div class="wall-days">
                @foreach ($week as $i => $day)
                    <article class="wall-day {{ $i === 0 ? 'is-today' : '' }}" wire:key="day-{{ $day['date']->toDateString() }}">
                        <button type="button" class="md-btn-text wall-day__head" @click="dayIndex = {{ $i }}; agendaOpen = true" aria-label="Ver agenda del {{ $day['date']->locale('es')->translatedFormat('l d F') }}"><span>{{ $i === 0 ? 'Hoy' : $day['date']->locale('es')->translatedFormat('D') }}</span><b>{{ $day['date']->day }}</b></button>
                        <div class="wall-day__items" x-data="wallDay({{ \Illuminate\Support\Js::from($day['rows']) }})" wire:key="rows-{{ md5(json_encode($day['rows'])) }}">
                            @forelse ($day['rows'] as $index => $row)
                                <button type="button" class="md-btn-text wall-row wall-row--{{ $row['kind'] }} {{ $row['done'] ? 'is-done' : '' }}" x-show="layers.{{ $row['kind'] }} && rows.filter(row => layers[row.kind]).findIndex(row => row.kind === {{ \Illuminate\Support\Js::from($row['kind']) }} && row.id === {{ \Illuminate\Support\Js::from($row['id']) }} && row.title === {{ \Illuminate\Support\Js::from($row['title']) }}) < limit" wire:click="openDetail('{{ $row['kind'] }}', '{{ $row['id'] }}')" title="{{ $row['title'] }}"><small>{{ $row['time'] ?? 'Día' }}</small><span>{{ $row['title'] }}</span></button>
                            @empty
                                <p class="wall-muted">Día libre</p>
                            @endforelse
                            <x-ui.action class="wall-control" variant="text" x-show="rows.filter(row => layers[row.kind]).length > limit" @click="dayIndex = {{ $i }}; agendaOpen = true"><span x-text="' +' + (rows.filter(row => layers[row.kind]).length - limit) + ' más'"></span></x-ui.action>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
        <section class="wall-cards" aria-label="Registros y seguimiento">
            <article class="wall-card wall-card--habits">
                <header><h2><i class="bi bi-check2-square" aria-hidden="true"></i> Hábitos · {{ \App\Livewire\Habit\HabitSettings::TIMES_OF_DAY[$block] }}</h2><x-ui.icon-action icon="bi-three-dots" label="Ver todos los hábitos" @click="habitsOpen = true" /></header>
                <div class="wall-habits">
                    @forelse ($habits->whereIn('time_of_day', [$block, 'anytime'])->take(3) as $habit)
                        <button type="button" class="md-btn-outlined wall-habit {{ in_array($habit->id, $completions) ? 'is-done' : '' }}" wire:click="toggleHabit({{ $habit->id }})" aria-pressed="{{ in_array($habit->id, $completions) ? 'true' : 'false' }}" wire:loading.attr="disabled">{{ $habit->icon }} {{ $habit->name }} @if(($habitStreaks[$habit->id] ?? 0) > 0)<small aria-hidden="true">🔥{{ $habitStreaks[$habit->id] }}</small>@endif</button>
                    @empty <p class="wall-muted">Sin hábitos en este momento del día.</p>@endforelse
                </div>
                <p class="wall-muted">{{ count($completions) }}/{{ $habits->count() }} completados hoy</p>
            </article>
            <article class="wall-card wall-card--water">
                <header><h2><i class="bi bi-droplet" aria-hidden="true"></i> Agua</h2><x-ui.icon-action icon="bi-three-dots" label="Ver hidratación" @click="waterOpen = true" /></header>
                <strong>{{ number_format($waterTotal, 0, ',', '.') }} ml / {{ number_format($waterGoal, 0, ',', '.') }} ml</strong>
                <div class="wall-quick">
                    @foreach ($drinkTypes->take(2) as $drink)
                        <button type="button" class="md-btn-tonal wall-control" wire:click="quickAddWater('{{ $drink->id }}', 250)" wire:loading.attr="disabled">{{ $drink->icon }} {{ $drink->name }} 250 ml</button>
                    @endforeach
                </div>
                <x-ui.progress :value="$waterGoal ? min(100, $waterTotal/$waterGoal*100) : 0" label="Meta de hidratación" />
            </article>
            <article class="wall-card wall-card--mood">
                <header><h2><i class="bi bi-emoji-smile" aria-hidden="true"></i> Ánimo</h2><x-ui.icon-action icon="bi-three-dots" label="Ver ánimo y energía" @click="moodOpen = true" /></header>
                <div class="wall-quick">@foreach ($moodStates->take(5) as $state)<button type="button" class="md-btn-text wall-emoji" wire:click="saveMood('{{ $state->id }}')" aria-label="{{ $state->text }}" wire:loading.attr="disabled">{{ $state->emoji }}</button>@endforeach</div>
                <div class="wall-trend" aria-label="Tendencia de los últimos siete días">@foreach ($trend as $point)<div><b>{{ $point['mood']?->emoji ?? '—' }}</b><small>{{ $point['date']->locale('es')->translatedFormat('D') }}</small><small>{{ $point['energy'] ? $point['energy'].'/5' : '—' }}</small></div>@endforeach</div>
            </article>
            <article class="wall-card wall-card--focus">
                <header><h2><i class="bi bi-flag" aria-hidden="true"></i> Foco y objetivos</h2></header>
                <x-ui.action class="wall-control" variant="tonal" icon="bi-play-fill" @click="$dispatch('panel-focus', {start: true, description: {{ \Illuminate\Support\Js::from($nextTasks->first()['title'] ?? '') }}})">Iniciar foco</x-ui.action>
                @forelse ($goals->take(2) as $goal)
                    @php($progress = \App\Support\GoalProgress::calculate($goal->numeric_goal, $goal->start_date, $goal->due_date))
                    <button type="button" class="md-btn-text wall-goal" @click="$dispatch('panel-goal', {id: {{ \Illuminate\Support\Js::from($goal->id) }}})"><b>{{ $goal->title }}</b><small>{{ $progress ? $progress['currentValue'].' / '.$progress['targetValue'] : 'Registrar avance' }}</small></button>
                    @if ($progress)<x-ui.progress :value="$progress['actualPercent']" :label="$goal->title" />@endif
                @empty <p class="wall-muted">Sin objetivos activos.</p>@endforelse
                @if ($goals->count() > 2)<x-ui.action class="wall-control" variant="text" @click="goalsOpen = true">Ver todos los objetivos</x-ui.action>@endif
            </article>
            <article class="wall-card wall-side">
                <x-ui.action class="wall-control" variant="text" icon="bi-cart3" @click="shoppingOpen = true">{{ $shopping->count() }} compras pendientes</x-ui.action>
                <x-ui.action class="wall-control" variant="text" icon="bi-shield-check" @click="avoidOpen = true">Hábitos a evitar</x-ui.action>
                <x-ui.action class="wall-control" variant="text" icon="bi-journal-text" @click="$dispatch('panel-quick', {kind: 'journal'})">Diario · Una línea de hoy</x-ui.action>
            </article>
        </section>
    </main>
    <x-ui.fab label="Crear tarea" event="task-editor" :detail="['action' => 'openForm']" module="home" :actions="[
        ['label' => 'Agregar plan', 'icon' => 'bi-map', 'event' => 'panel-plan'],
        ['label' => 'Agregar compra', 'icon' => 'bi-cart-plus', 'event' => 'panel-shopping'],
        ['label' => 'Registrar bebida', 'icon' => 'bi-droplet', 'event' => 'panel-quick', 'detail' => ['kind' => 'water']],
        ['label' => 'Registrar ánimo', 'icon' => 'bi-emoji-smile', 'event' => 'panel-quick', 'detail' => ['kind' => 'mood']],
        ['label' => 'Escribir diario', 'icon' => 'bi-journal-text', 'event' => 'panel-quick', 'detail' => ['kind' => 'journal']],
        ['label' => 'Registrar avance', 'icon' => 'bi-flag', 'event' => 'panel-goals'],
        ['label' => 'Agregar hábito', 'icon' => 'bi-check2-square', 'event' => 'panel-habit'],
    ]" />
    @include('livewire.home.panel.sheets')
    <div class="wall-ambient" x-show="ambient" x-cloak role="dialog" aria-modal="true" aria-label="Modo ambiente" x-md-surface="ambient" @md-surface-close="ambient = false"><strong x-text="clock"></strong><span x-text="dateLabel"></span>@if($next)<p>{{ $next['time'] }} · {{ $next['title'] }}</p>@endif<x-ui.action class="wall-control" variant="tonal" @click="ambient = false">Salir del modo ambiente</x-ui.action></div>
    <livewire:task.task-editor :edit-task="$editTask"  :key="'wall-task-task-editor'" />
    <livewire:home.panel.shopping-editor  :key="'wall-home-panel-shopping-editor'" />
    <livewire:home.panel.habit-editor  :key="'wall-home-panel-habit-editor'" />
    <livewire:home.panel.plan-editor  :key="'wall-home-panel-plan-editor'" />
    <livewire:home.panel.goal-editor  :key="'wall-home-panel-goal-editor'" />
    <livewire:home.panel.quick-editor  :key="'wall-home-panel-quick-editor'" />
    <livewire:home.panel.focus-timer  :key="'wall-home-panel-focus-timer'" />
    @include('livewire.habit.partials.completion-feedback')
    @include('livewire.task.partials.completion-celebration')
    <div x-data="{ showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
        @include('livewire.task.partials.recurring-completion-dialog', ['panelDialogs' => true])
    </div>
    @include('livewire.habit.partials.action-prompt-dialog')
    @include('livewire.mood.partials.progressive', ['panelDialogs' => true])
</div>
