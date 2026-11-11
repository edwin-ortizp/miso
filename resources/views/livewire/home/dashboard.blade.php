<div data-module="home" class="lt-page" x-data="{ showRecurringDialog: $wire.entangle('showRecurringCompletion') }">
    <x-page-header :subtitle="ucfirst(\Carbon\Carbon::parse($selectedDate)->translatedFormat('l d \d\e F'))" />

    {{-- CHG-072: bienvenida al volver tras una ausencia. --}}
    <livewire:home.welcome-back />

    {{-- CHG-071: resumen del domingo. --}}
    <livewire:home.weekly-insights-card />

    {{-- CHG-069: check-in de 20 s. --}}
    <div class="d-flex justify-content-end mb-2">
        <x-ui.action type="button" variant="tonal" wire:click="$set('checkin', true)"><i class="bi bi-lightning-charge" aria-hidden="true"></i> Check-in</x-ui.action>
    </div>
    @include('livewire.home.partials.checkin-sheet')

    @if ($vehicleAlerts->isNotEmpty())
        <a href="{{ route('vehicles') }}" class="md-card-outlined d-block text-decoration-none mb-3" style="color: inherit; border-color: var(--md-custom-color-warning);">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div class="d-flex gap-3"><div class="md-card-icon" style="background: var(--md-custom-color-warning-container); color: var(--md-custom-color-on-warning-container);"><i class="bi bi-wrench-adjustable"></i></div><div><h2 class="md-title-medium mb-1">Atención para tus vehículos</h2><p class="md-body-small mb-0" style="color: var(--md-sys-color-on-surface-variant);">{{ $vehicleAlerts->count() }} mantenimiento{{ $vehicleAlerts->count() > 1 ? 's' : '' }} próximo{{ $vehicleAlerts->count() > 1 ? 's' : '' }} o vencido{{ $vehicleAlerts->count() > 1 ? 's' : '' }}.</p></div></div>
                <div class="d-flex flex-column gap-1">@foreach ($vehicleAlerts as $alert)<span class="md-label-small"><strong>{{ $alert->vehicle->name }}:</strong> {{ $alert->template->name }}</span>@endforeach</div>
            </div>
        </a>
    @endif

    {{-- Bento grid: mixed-size cards, full width --}}
    <div class="row g-3">
        @if ($buddy)
            <div class="col-12">
                <x-game.daily-buddy :buddy="$buddy" />
            </div>
        @endif
        <div class="col-12 col-lg-7">
            @include('livewire.home.partials.quick-log')
        </div>
        <div class="col-12 col-lg-5">
            @include('livewire.home.partials.habits-today')
        </div>

        <div class="col-12 col-lg-5">
            @include('livewire.home.partials.tasks-today')
        </div>
        <div class="col-12 col-lg-7">
            @include('livewire.home.partials.weekly-chart')
        </div>

        <div class="col-12 col-md-6 col-lg-7">
            @include('livewire.home.partials.meals-today')
        </div>
        <div class="col-12 col-md-6 col-lg-5">
            <div class="md-card-elevated h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h2 class="md-title-small mb-0" style="color: var(--md-sys-color-on-surface);">
                        <i class="bi bi-journal-text" style="color: var(--md-sys-color-secondary);"></i> Bitácora del día
                    </h2>
                    <a href="{{ route('journal', ['date' => $selectedDate]) }}" class="md-btn-text ms-auto" style="height: 32px; padding: 0 10px;">Abrir diario</a>
                </div>
                @if ($journalEntry)
                    <p class="home-journal-excerpt md-body-small mb-2" style="color: var(--md-sys-color-on-surface);">{{ Str::limit($journalEntry->text, 220) }}</p>
                    <a href="{{ route('journal', ['date' => $selectedDate]) }}" class="md-btn-text">Continuar escribiendo</a>
                @else
                    <p class="md-body-small mb-2" style="color: var(--md-sys-color-on-surface-variant);">Todavía no has escrito una entrada para este día.</p>
                    <a href="{{ route('journal', ['date' => $selectedDate]) }}" class="md-btn-tonal"><i class="bi bi-pencil"></i> Escribir entrada</a>
                @endif
            </div>
        </div>
    </div>

    <x-ui.fab compact label="Crear tarea" event="task-editor" :detail="['action' => 'openForm']" module="home" :actions="[
        ['label' => 'Agregar plan', 'icon' => 'bi-map', 'event' => 'panel-plan'],
        ['label' => 'Registrar sesión de enfoque', 'icon' => 'bi-stopwatch', 'event' => 'panel-focus-manual'],
        ['label' => 'Agregar compra', 'icon' => 'bi-cart-plus', 'event' => 'panel-shopping'],
        ['label' => 'Registrar bebida', 'icon' => 'bi-droplet', 'event' => 'panel-quick', 'detail' => ['kind' => 'water']],
        ['label' => 'Registrar ánimo', 'icon' => 'bi-emoji-smile', 'event' => 'panel-quick', 'detail' => ['kind' => 'mood']],
        ['label' => 'Escribir diario', 'icon' => 'bi-journal-text', 'event' => 'panel-quick', 'detail' => ['kind' => 'journal']],
        ['label' => 'Registrar avance', 'icon' => 'bi-flag', 'href' => route('goals')],
        ['label' => 'Agregar hábito', 'icon' => 'bi-check2-square', 'event' => 'panel-habit'],
    ]" />
    <livewire:task.task-editor :key="'home-task-editor'" />
    <livewire:home.panel.shopping-editor :key="'home-panel-shopping-editor'" />
    <livewire:home.panel.habit-editor :key="'home-panel-habit-editor'" />
    <livewire:home.panel.plan-editor :key="'home-panel-plan-editor'" />
    <livewire:home.panel.quick-editor :key="'home-panel-quick-editor'" />
    <livewire:home.panel.focus-timer :key="'home-panel-focus-timer'" />

    @include('livewire.habit.partials.completion-feedback')
    @include('livewire.task.partials.completion-celebration')
    @include('livewire.task.partials.recurring-completion-dialog')
    @include('livewire.habit.partials.action-prompt-dialog')
    @include('livewire.mood.partials.progressive')
</div>
