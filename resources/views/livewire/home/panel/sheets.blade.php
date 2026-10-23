<x-ui.dialog state="agendaOpen" title="Agenda" size="lg" icon="bi-calendar-day">
    @foreach ($week as $i => $day)
        <section x-show="dayIndex === {{ $i }}"><h3 class="md-title-medium">{{ $day['date']->translatedFormat('l d F') }}</h3>
            @forelse ($day['rows'] as $row)
                <button type="button" class="md-btn-text wall-sheet-row {{ $row['done'] ? 'is-done' : '' }}" x-show="layers.{{ $row['kind'] }}" @click="agendaOpen = false" wire:click="openDetail('{{ $row['kind'] }}', '{{ $row['id'] }}')"><b>{{ $row['time'] ?? 'Sin hora' }}</b><span>{{ $row['title'] }}</span></button>
            @empty <p>Día libre: nada programado.</p>@endforelse
        </section>
    @endforeach
    <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="agendaOpen = false">Cerrar</x-ui.action></x-slot:actions>
</x-ui.dialog>
<x-ui.dialog state="habitsOpen" title="Hábitos de hoy" size="lg" icon="bi-check2-square">
    @foreach (\App\Livewire\Habit\HabitSettings::TIMES_OF_DAY as $key => $label)
        <h3 class="md-title-medium">{{ $label }}</h3>
        @forelse ($habits->where('time_of_day', $key) as $habit)
            <button type="button" class="md-btn-text wall-sheet-row {{ in_array($habit->id, $completions) ? 'is-done' : '' }}" wire:click="toggleHabit({{ $habit->id }})" aria-pressed="{{ in_array($habit->id, $completions) ? 'true' : 'false' }}" wire:loading.attr="disabled"><span>{{ $habit->icon }} {{ $habit->name }}</span><small>{{ $habit->base_time ? substr($habit->base_time, 0, 5) : 'Sin hora' }} · Racha {{ $habitStreaks[$habit->id] ?? 0 }}</small></button>
        @empty <p class="wall-muted">Sin hábitos en este bloque.</p>@endforelse
    @endforeach
    <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="habitsOpen = false">Cerrar</x-ui.action></x-slot:actions>
</x-ui.dialog>
<x-ui.dialog state="waterOpen" title="Hidratación de hoy" size="lg" icon="bi-droplet">
    <p>{{ number_format($waterTotal, 0, ',', '.') }} ml efectivos · Meta {{ $waterGoal }} ml</p>
    <h3 class="md-title-medium">Últimos registros</h3>
    @forelse ($waterLogs as $log)<p>{{ $log->time }} · {{ $log->drink_type }} · {{ $log->amount }} ml</p>@empty<p>Sin bebidas registradas hoy.</p>@endforelse
    <div class="wall-drinks">@foreach ($drinkTypes as $drink)<section><h3 class="md-title-medium">{{ $drink->icon }} {{ $drink->name }}</h3><div class="d-flex flex-wrap gap-2">@foreach ([150, 250, 500] as $ml)<button type="button" class="md-btn-tonal wall-control" wire:click="quickAddWater('{{ $drink->id }}', {{ $ml }})" wire:loading.attr="disabled">{{ $ml }} ml</button>@endforeach</div></section>@endforeach</div>
    <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="waterOpen = false">Cerrar</x-ui.action></x-slot:actions>
</x-ui.dialog>
<x-ui.dialog state="moodOpen" title="Ánimo y energía" size="lg" icon="bi-emoji-smile">
    <div class="wall-quick">@foreach ($moodStates as $state)<button type="button" class="md-btn-tonal" wire:click="saveMood('{{ $state->id }}')" wire:loading.attr="disabled">{{ $state->emoji }} {{ $state->text }}</button>@endforeach</div>
    <x-ui.action class="wall-control" variant="text" wire:click="openMoodCatalog" @click="moodOpen = false">Más emociones</x-ui.action>
    <h3 class="md-title-medium">Energía</h3><div class="wall-quick">@foreach ([1 => 'Muy baja', 2 => 'Baja', 3 => 'Media', 4 => 'Alta', 5 => 'Muy alta'] as $value => $label)<button type="button" class="md-btn-outlined" wire:click="saveEnergy({{ $value }})" wire:loading.attr="disabled">{{ $value }} · {{ $label }}</button>@endforeach</div>
    <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="moodOpen = false">Cerrar</x-ui.action></x-slot:actions>
</x-ui.dialog>
<x-ui.dialog state="goalsOpen" title="Objetivos activos" size="lg" icon="bi-flag">
    @forelse ($goals as $goal)<x-ui.action class="wall-control" variant="text" @click="goalsOpen = false; $dispatch('panel-goal', {id: {{ \Illuminate\Support\Js::from($goal->id) }}})">{{ $goal->title }}</x-ui.action>@empty<p>Sin objetivos activos.</p>@endforelse
    <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="goalsOpen = false">Cerrar</x-ui.action></x-slot:actions>
</x-ui.dialog>
<x-ui.dialog state="shoppingOpen" title="Lista de compras" size="lg" icon="bi-cart3">
    @forelse ($shopping as $item)<div class="wall-sheet-row"><span>{{ $item->name }} · {{ $item->to_buy }}</span><x-ui.action class="wall-control" variant="text" @click="shoppingOpen = false; $dispatch('panel-shopping', {id: {{ \Illuminate\Support\Js::from($item->id) }}})">Marcar comprado</x-ui.action></div>@empty<p>Sin compras pendientes.</p>@endforelse
    <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="shoppingOpen = false">Cerrar</x-ui.action></x-slot:actions>
</x-ui.dialog>
<x-ui.dialog state="avoidOpen" title="Hábitos a evitar" size="lg" icon="bi-shield-check">
    @forelse ($negativeHabits as $habit)
        <div class="wall-sheet-row"><span><b>{{ $habit->name }}</b><small class="d-block">{{ isset($lastRelapses[$habit->id]) ? floor(\Carbon\Carbon::createFromTimestamp($lastRelapses[$habit->id])->diffInDays(now())).' días desde el último registro' : 'Sin recaídas registradas' }}</small></span><x-ui.action class="wall-control" variant="text" @click="avoidOpen = false; $dispatch('panel-quick', {kind: 'avoid', id: {{ \Illuminate\Support\Js::from((string) $habit->id) }}})">Registrar recaída</x-ui.action></div>
    @empty<p>Sin hábitos a evitar configurados.</p>@endforelse
    <x-slot:actions><x-ui.action class="wall-control" variant="text" @click="avoidOpen = false">Cerrar</x-ui.action></x-slot:actions>
</x-ui.dialog>
@if ($detail)
    <x-ui.dialog state="$wire.showDetail" title="Detalle de agenda" size="lg">
        <h3 class="md-title-large">{{ $detailKind === 'person' ? $detail->displayName() : ($detail->title ?? 'Comidas del día') }}</h3>
        @if ($detailKind === 'task')
            <p>{{ $detail->status?->name }} · {{ $detail->category }}</p><p>{{ $detail->start_date?->format($detail->start_is_date ? 'd/m/Y' : 'd/m/Y H:i') }} → {{ $detail->end_date?->format($detail->end_is_date ? 'd/m/Y' : 'd/m/Y H:i') }}</p>
            <p class="wall-preserve">{{ $detail->description }}</p>
            <div class="wall-quick"><x-ui.action class="wall-control" variant="tonal" wire:click="toggleTask('{{ $detail->id }}')" wire:loading.attr="disabled">{{ $detail->completed ? 'Reabrir' : 'Completar' }}</x-ui.action><x-ui.action class="wall-control" variant="text" @click="$wire.showDetail = false; $dispatch('task-editor', {action: 'openForm', id: {{ \Illuminate\Support\Js::from($detail->id) }}})">Editar</x-ui.action><x-ui.action class="wall-control" variant="text" @click="$wire.showDetail = false; $dispatch('panel-quick', {kind: 'snooze', id: {{ \Illuminate\Support\Js::from($detail->id) }}})">Posponer</x-ui.action><x-ui.action class="wall-control" variant="text" @click="$wire.showDetail = false; $dispatch('panel-focus', {start: true, description: {{ \Illuminate\Support\Js::from($detail->title) }}})">Foco</x-ui.action></div>
        @elseif ($detailKind === 'plan')
            <p>{{ $detail->scheduled_on?->format('d/m/Y') }} · {{ $detail->city }} · {{ $detail->address }}</p><p>{{ $detail->relationships->map->displayName()->implode(', ') }}</p><p class="wall-preserve">{{ $detail->notes }}</p><p>{{ $detail->visits->count() }} visitas registradas</p>
            <x-ui.action class="wall-control" variant="tonal" @click="$wire.showDetail = false; $dispatch('panel-plan', {id: {{ \Illuminate\Support\Js::from($detail->id) }}, visit: true})">Registrar visita</x-ui.action>
            <x-ui.action class="wall-control" variant="text" @click="$wire.showDetail = false; $dispatch('panel-plan', {id: {{ \Illuminate\Support\Js::from($detail->id) }}})">Editar plan</x-ui.action>
        @elseif ($detailKind === 'person')
            <p>{{ $detail->circle?->name }}</p><p>Cumpleaños: {{ $detail->birthday()?->nextOccurrence()->format('d/m/Y') ?? 'Sin fecha' }}</p><p>Último contacto: {{ $detail->last_contact_at?->format('d/m/Y') ?? 'Sin registrar' }}</p><p class="wall-preserve">{{ $detail->general_notes }}</p><x-ui.action class="wall-control" variant="text" :href="route('relationships.show', $detail->id)">Abrir persona</x-ui.action>
        @elseif ($detailKind === 'meal')
            <p>{{ $detail->date->format('d/m/Y') }} · {{ \App\Livewire\Home\Dashboard::MEAL_TYPES[$detail->meal_type] ?? $detail->meal_type }} · {{ $detail->status }}</p>
            @foreach ($detail->items as $item)<p>{{ $item->recipe?->name ?? $item->preparation?->recipe?->name ?? $item->name }} · {{ $item->portions }} porciones</p>@endforeach
            <p>{{ $detail->notes }}</p>
        @endif
        <x-slot:actions><x-ui.action class="wall-control" variant="text" wire:click="closeDetail">Cerrar</x-ui.action></x-slot:actions>
    </x-ui.dialog>
@endif
