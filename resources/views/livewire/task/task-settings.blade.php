<x-module-shell module="tasks" archetype="settings">
    <x-slot:actions>
        @if ($section !== 'planning')
            <x-module-actions :primary="$section === 'statuses'
                ? ['label' => 'Nuevo estado', 'icon' => 'bi-plus-lg', 'action' => 'openForm']
                : ['label' => 'Nueva categoría', 'icon' => 'bi-plus-lg', 'action' => 'openForm']" />
        @endif
    </x-slot:actions>

    @if ($message)
        <div class="md-card-filled mb-3 py-3" role="status" aria-live="polite">{{ $message }}</div>
    @endif

    <div class="task-settings-workspace">
        <div>
            @include(match ($section) {
                'statuses' => 'livewire.task.partials.settings-statuses',
                'planning' => 'livewire.task.partials.settings-planning',
                default => 'livewire.task.partials.settings-categories',
            })
        </div>
        <nav class="task-settings-nav" aria-label="Secciones de Ajustes de tareas">
            <a href="{{ route('tasks.settings') }}" wire:navigate class="task-settings-nav__item {{ $section === 'categories' ? 'is-active' : '' }}" @if ($section === 'categories') aria-current="page" @endif><i class="bi bi-tags" aria-hidden="true"></i> Categorías</a>
            <a href="{{ route('tasks.settings.statuses') }}" wire:navigate class="task-settings-nav__item {{ $section === 'statuses' ? 'is-active' : '' }}" @if ($section === 'statuses') aria-current="page" @endif><i class="bi bi-kanban" aria-hidden="true"></i> Estados</a>
            <a href="{{ route('tasks.settings.planning') }}" wire:navigate class="task-settings-nav__item {{ $section === 'planning' ? 'is-active' : '' }}" @if ($section === 'planning') aria-current="page" @endif><i class="bi bi-calendar-check" aria-hidden="true"></i> Horario para planificar</a>
        </nav>
    </div>
</x-module-shell>
