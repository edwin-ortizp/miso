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

    <div class="module-settings-workspace">
        <div>
            @include(match ($section) {
                'statuses' => 'livewire.task.partials.settings-statuses',
                'planning' => 'livewire.task.partials.settings-planning',
                default => 'livewire.task.partials.settings-categories',
            })
        </div>
        <x-settings-sections label="Secciones de Ajustes de tareas" :items="\App\Livewire\Task\TaskSettings::SECTIONS" :current="$section" />
    </div>
</x-module-shell>
