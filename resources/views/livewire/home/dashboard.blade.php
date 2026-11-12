<div data-module="home" class="lt-page" x-data="{ showRecurringDialog: $wire.entangle('showRecurringCompletion') }">

    @php
        $experience = app(\App\Services\Experience\PersonalExperience::class);
        $visibleModules = array_fill_keys(array_filter(array_keys($experience->catalog()), fn ($key) => $experience->visible($key)), true);
        $showModule = fn ($key) => isset($visibleModules[$key]);
        $experienceState = $experience->state((int) auth()->id());
    @endphp
    @if (empty($experienceState['started_at']))
        <p><a class="md-btn-text" href="{{ route('onboarding') }}" wire:navigate>Conoce MiSo y prepara tu primer hábito</a></p>
    @endif
    {{-- CHG-072: bienvenida al volver tras una ausencia. --}}
    <livewire:home.welcome-back />

    {{-- CHG-071: resumen del domingo. --}}
    @if (count($visibleModules) === count($experience->catalog()))<livewire:home.weekly-insights-card />@endif

    {{-- CHG-075: bienvenida con el compañero, la fecha y el check-in de 20 s (CHG-069) como su pedido. --}}
    @if ($showModule('game'))
    <x-game.daily-buddy variant="hero" class="mb-3" :buddy="$buddy"
        :date="ucfirst(\Carbon\Carbon::parse($selectedDate)->translatedFormat('l d \d\e F'))"
        :checkin-done="$lastMood && $lastEnergy" :streak="$streak" :challenge="$challenge"
        :checkin-available="$showModule('mood')" :night="$isNight" :close-href="$dayCloseHref" />
    @else
        <section class="md-card-filled mb-3" aria-label="Tu día">
            <h1 class="md-headline-medium">Tu día, a tu ritmo</h1>
            <p>Encuentra tu siguiente paso y registra lo que hayas hecho.</p>
            @if ($showModule('mood'))<x-ui.action variant="tonal" wire:click="$set('checkin', true)">¿Cómo vas hoy?</x-ui.action>@endif
            <a class="md-btn-text" href="{{ route('settings.section', 'modules') }}" wire:navigate>Personalizar módulos</a>
        </section>
    @endif
    @if ($showModule('mood')) @include('livewire.home.partials.checkin-sheet') @endif

    @if ($showModule('vehicles') && $vehicleAlerts->isNotEmpty())
        <a href="{{ route('vehicles') }}" class="md-card-outlined d-block text-decoration-none mb-3" style="color: inherit; border-color: var(--md-custom-color-warning);">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div class="d-flex gap-3"><div class="md-card-icon" style="background: var(--md-custom-color-warning-container); color: var(--md-custom-color-on-warning-container);"><i class="bi bi-wrench-adjustable"></i></div><div><h2 class="md-title-medium mb-1">Atención para tus vehículos</h2><p class="home-muted md-body-small mb-0">{{ $vehicleAlerts->count() }} mantenimiento{{ $vehicleAlerts->count() > 1 ? 's' : '' }} próximo{{ $vehicleAlerts->count() > 1 ? 's' : '' }} o vencido{{ $vehicleAlerts->count() > 1 ? 's' : '' }}.</p></div></div>
                <div class="d-flex flex-column gap-1">@foreach ($vehicleAlerts as $alert)<span class="md-label-small"><strong>{{ $alert->vehicle->name }}:</strong> {{ $alert->template->name }}</span>@endforeach</div>
            </div>
        </a>
    @endif

    {{-- Bento grid: mixed-size cards, full width --}}
    <div class="row g-3">
        @if ($showModule('water') || $showModule('mood'))
        <div class="col-12 col-lg-7">
            @include('livewire.home.partials.quick-log')
        </div>
        @endif
        @if ($showModule('habits'))
        <div class="col-12 col-lg-5">
            @include('livewire.home.partials.habits-today')
        </div>
        @endif

        @if ($showModule('tasks'))
        <div class="col-12 col-lg-5">
            @include('livewire.home.partials.tasks-today')
        </div>
        @endif
        @if ($showModule('water') && $showModule('habits'))
        <div class="col-12 col-lg-7">
            @include('livewire.home.partials.weekly-chart')
        </div>
        @endif

        @if ($showModule('meals'))
        <div class="col-12 col-md-6 col-lg-7">
            @include('livewire.home.partials.meals-today')
        </div>
        @endif
        @if ($showModule('journal'))
        <div class="col-12 col-md-6 col-lg-5">
            <div class="md-card-elevated h-100">
                <x-home.card-head icon="bi-journal-text" tone="secondary" title="Bitácora del día"
                    :href="route('journal', ['date' => $selectedDate])" action="Abrir diario" />
                @if ($journalEntry)
                    <p class="home-journal-excerpt md-body-small mb-2">{{ Str::limit($journalEntry->text, 220) }}</p>
                    <a href="{{ route('journal', ['date' => $selectedDate]) }}" class="md-btn-text">Continuar escribiendo</a>
                @else
                    <p class="home-muted md-body-small mb-2">Todavía no has escrito una entrada para este día.</p>
                    <a href="{{ route('journal', ['date' => $selectedDate]) }}" class="md-btn-tonal"><i class="bi bi-pencil"></i> Escribir entrada</a>
                @endif
            </div>
        </div>
        @endif
    </div>

    @php
        $createActions = collect([
            ['module' => 'tasks', 'label' => 'Crear tarea', 'icon' => 'bi-plus-lg', 'event' => 'task-editor', 'detail' => ['action' => 'openForm']],
            ['module' => 'plans', 'label' => 'Agregar plan', 'icon' => 'bi-map', 'event' => 'panel-plan'],
            ['module' => 'pomodoro', 'label' => 'Registrar sesión de enfoque', 'icon' => 'bi-stopwatch', 'event' => 'panel-focus-manual'],
            ['module' => 'meals', 'label' => 'Agregar compra', 'icon' => 'bi-cart-plus', 'event' => 'panel-shopping'],
            ['module' => 'water', 'label' => 'Registrar bebida', 'icon' => 'bi-droplet', 'event' => 'panel-quick', 'detail' => ['kind' => 'water']],
            ['module' => 'mood', 'label' => 'Registrar ánimo', 'icon' => 'bi-emoji-smile', 'event' => 'panel-quick', 'detail' => ['kind' => 'mood']],
            ['module' => 'journal', 'label' => 'Escribir diario', 'icon' => 'bi-journal-text', 'event' => 'panel-quick', 'detail' => ['kind' => 'journal']],
            ['module' => 'habits', 'label' => 'Agregar hábito', 'icon' => 'bi-check2-square', 'event' => 'panel-habit'],
        ])->filter(fn ($item) => $showModule($item['module']))->values();
        $primaryCreate = $createActions->first();
    @endphp
    @if ($showModule('goals'))
        <a class="md-btn-text" href="{{ route('goals') }}" wire:navigate>Consultar mis objetivos y registrar avances</a>
    @endif
    @if ($primaryCreate)
        <x-ui.fab compact :label="$primaryCreate['label']" :event="$primaryCreate['event']" :detail="$primaryCreate['detail'] ?? null" module="home" :actions="$createActions->slice(1)->values()->all()" />
    @else
        <p>Tu Inicio está despejado. <a href="{{ route('settings.section', 'modules') }}" wire:navigate>Elige los módulos que quieres ver.</a></p>
    @endif
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
    @if ($showModule('mood')) @include('livewire.mood.partials.progressive') @endif
</div>
