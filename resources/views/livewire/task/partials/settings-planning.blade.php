@php
    use App\Services\Task\PlanningHours;
@endphp

<form wire:submit="savePlanning" class="md-card-outlined task-planning-hours" id="task-settings-planning">
    <div class="md-card-header">
        <span class="md-card-icon md-card-icon--primary"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
        <h3 class="md-title-medium">Horario para planificar</h3>
    </div>
    <div class="md-card-content d-flex flex-column gap-3">
        <p class="md-body-medium mb-0">Cuándo quieres que te proponga horarios para tus tareas. No incluye citas de salud ni planes sin hora.</p>

        @foreach (PlanningHours::DAYS as $day => $dayName)
            <fieldset class="task-planning-hours__row" wire:key="planning-day-{{ $day }}">
                <legend class="visually-hidden">{{ $dayName }}</legend>
                <x-ui.switch :name="'planningDays.'.$day.'.enabled'" :id="'planning-day-'.$day" :label="$dayName"
                             :help="($planningDays[$day]['enabled'] ?? false) ? null : 'Sin planificar'"
                             :checked="(bool) ($planningDays[$day]['enabled'] ?? false)" wire:model.live="planningDays.{{ $day }}.enabled" />
                @if ($planningDays[$day]['enabled'] ?? false)
                    <div class="md-field-pair task-planning-hours__times">
                        <x-ui.field type="time" :name="'planningDays.'.$day.'.start'" :label="'Desde · '.$dayName" wire:model="planningDays.{{ $day }}.start" />
                        <x-ui.field type="time" :name="'planningDays.'.$day.'.end'" :label="'Hasta · '.$dayName" wire:model="planningDays.{{ $day }}.end" />
                    </div>
                @endif
            </fieldset>
        @endforeach

        @unless (collect($planningDays)->contains(fn ($row) => $row['enabled'] ?? false))
            <p class="md-body-small mb-0" role="status">No se propondrán horarios mientras todos los días estén sin planificar.</p>
        @endunless

        <x-ui.select name="planningMargin" label="Margen entre bloques" :selected="$planningMargin" wire:model="planningMargin"
                     :options="collect(PlanningHours::MARGINS)->mapWithKeys(fn ($minutes) => [$minutes => $minutes === 0 ? 'Sin margen' : $minutes.' min'])->all()" />

        <div class="d-flex justify-content-between gap-2 flex-wrap">
            <x-ui.action variant="text" type="button" wire:click="restorePlanning">Restaurar valores</x-ui.action>
            <x-ui.action type="submit" wire:loading.attr="disabled">Guardar</x-ui.action>
        </div>
    </div>
</form>
